# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

Sagra is a Laravel + Filament admin application for running a food-festival ("sagra") point-of-sale. It manages one or more independent order **queues/lines** ("file"), products with stock and ingredient-based availability, and order creation/printing. There is no separate customer-facing frontend — everything is a Filament panel at `/`.

## Environment

Development runs entirely through **Laravel Sail** (Docker). Containers: `laravel.test` (app), `mysql`, `redis`, `mailpit`. Alias `sail='./vendor/bin/sail'` is assumed in examples below.

```bash
sail up -d                     # start containers
sail down                      # stop containers
sail artisan migrate --seed    # migrate + seed
sail artisan horizon           # start queue worker (Horizon UI at /horizon)
sail npm run dev / build       # Vite frontend assets (Tailwind + Filament theme)
```

## Common commands

```bash
sail artisan test                      # run full test suite (Pest)
sail artisan test --parallel           # same, one database per process — much faster
sail artisan test --filter=SomeTest    # run a single test file or name
sail bin phpstan analyse                # static analysis (Larastan, level 8, app/ only — see phpstan.neon)
sail bin phpmd app text phpmdruleset.xml   # mess detector (excludes tests/, storage/)
sail bin pint                          # code style (Laravel Pint)
```

### Test setup

Tests are **Pest 4** and run against a real MySQL database named `testing` (`phpunit.xml` sets `DB_DATABASE=testing`), not SQLite — the suite asserts on MySQL-specific behaviour (foreign key delete rules, unique constraint violations by error code, `SELECT ... FOR UPDATE`). If the database does not exist yet:

```bash
docker exec sagra-mysql-1 mysql -uroot -ppassword \
  -e "CREATE DATABASE testing; GRANT ALL ON \`testing%\`.* TO 'sail'@'%';"
```

`--parallel` needs `CREATE`/`DROP` on `*.*` for the `sail` user, since Laravel creates one `testing_test_N` database per process. Running several test processes against the *same* database will corrupt each other's state — use `--parallel` (or a distinct `DB_DATABASE`) rather than launching concurrent runs by hand.

`tests/Pest.php` binds `RefreshDatabase` to everything under `Feature` (so migrations, including the one that seeds the `admin`/`cassa` roles, run automatically) and exposes helpers: `admin()`, `cassa()`, `actingAsAdmin()`, `actingAsCassa()`, `userWithRole()`, `setConfig()` (writes a config value *and* flushes the memoized cache), and `countQueries(Closure)` for the N+1 regression tests. Every model has a working factory with useful states (`Product::factory()->backorder()`, `Queue::factory()->resetAt(...)`, `OrderItem::factory()->of($product, $qty)`, …).

## Architecture

### Domain model

- `Queue` — an independent ordering line ("Fila 1", "Fila 2", ...). Holds its own `order_number` counter, incremented per created order, so numbering is per-queue, not global. `Product`s are assigned to queues via a `product_queue` pivot (many-to-many), so different lines can sell different subsets of products.
- **There is no "default" flag** on `Queue` or `Logo`: both tables carry an `order` column driven by Filament's drag-and-drop `->reorderable('order')`, and the *lowest* position wins. Read them through the model helpers — `Queue::defaultQueue()` (first **enabled** queue, ties broken by `id`), `Queue::ordered()` / `Queue::enabledOrdered()` for dropdown lists, `Logo::defaultLogo()` for the logo printed on tickets — never `whereIsDefault(true)`, which no longer exists.
- `Order` → `hasMany` `OrderItem`. `OrderItem` snapshots product name/price/quantity/amount at creation time (not a live reference), and belongs to `Product` (nullable — product can later be deleted).
- `Product` ↔ `Ingredient` many-to-many via `product_ingredient` pivot with a `qty` column (how much of that ingredient one unit of the product consumes).
- `Config` is a key/value settings table used for the app timezone, `max_qty`, and toggles like `free` (no-payment) and `change_price`. **Read it via `Config::value('code')` / `Config::intValue('code', $default)`**, never `Config::whereCode(...)->first()->config_value` — the former memoizes per request/job (the raw form was being re-queried once per exported row and twice per repeater row) and `intValue` floors the result so a blank or non-numeric value can't collapse a loop bound to zero. The cache self-invalidates on save/delete and is flushed at the start of every queued job.
- `Order` and `OrderItem` use `SoftDeletes`; deleting an `Order` cascades to soft-delete its `orderItems` (see `Order::booted()`).

### Order creation flow

Orders are created **only** from the quick till: `OrderResource` has no `create` page (the classic Filament create form was removed), so `ListOrders` and the table's empty state link to `quick-create` instead of a `CreateAction`. `OrderResource::form()` is now used for edit/view/print only — its `queue_id` is a `Hidden` field, the queue is chosen in the till.

`QuickCreateOrder` (`app/Filament/Resources/OrderResource/Pages/QuickCreateOrder.php`) is a Filament/Livewire page holding the in-progress cart as plain component state — **not** Eloquent models:

```php
/** @var array<int, array{item_id: string, product_id: int, quantity: int, note: string|null}> */
public array $items = [];
```

This `$items` shape is threaded through the whole service layer. All cart/stock/enrichment logic is delegated to `OrderManagementService` (`app/Services/OrderManagementService.php`), a thin facade composing three single-purpose services:

- `CartService` — pure array operations on `$items` (add/remove/increase/decrease/split/count/total). No persistence, no Eloquent writes.
- `StockService` — availability checks that account for what's *already in the cart* (not yet persisted): `getRemainingStock`, `hasInsufficientIngredients`, `isProductOutOfStock`. A product's `backorder` flag bypasses all stock checks.
- `ProductEnrichmentService` — decorates products/cart items with computed display fields (remaining stock, out-of-stock flags, sort order, row totals) for the Livewire view.

Actual persistence only happens in `QuickCreateOrder::createOrder()`: within a DB transaction it locks the queue row, increments `order_number`, creates the `Order` + `OrderItem`s, and decrements `Product`/`Ingredient` stock. If you change cart/stock semantics, keep the three services and the page's persistence step in sync — the services never touch the database themselves.

### Concurrency: multiple tills hit the same rows

Several cashiers use this simultaneously on one database, so every write to a shared counter or stock column must be atomic. Two rules, both of which have already been violated and fixed here:

- **Per-queue order numbering** must read the counter under a row lock — `Queue::whereKey($id)->lockForUpdate()->first()` inside the transaction. A plain `find()` lets two tills read the same `order_number` and print two tickets with the same number.
- **Stock changes** must go through `increment()`/`decrement()` (which emit `stock = stock - ?` in SQL), never `$model->stock -= $n; $model->save()`. The read-modify-write form silently loses one of two concurrent adjustments.

This applies to `QuickCreateOrder::createOrder()` — the only creation path left — plus `EditOrder`'s save and delete hooks.

### Importer: read cast values, not raw CSV keys

In `ProductImporter`, Filament runs `remapData()` then `castData()` *before* `resolveRecord()`, writing the cast value under the column's **canonical name** (`$this->data['backorder']`). Reading `$this->data[$this->columnMap['backorder']]` gets the untouched CSV string instead, where `(bool) 'FALSE'` is `true`. Also note `resolveRecord()` returning `null` makes Filament skip validation, fill, save *and* `afterSave()` for that row — which is why the create path syncs queues itself.

### Filament resources — query() is shared between read and write

Resources live in `app/Filament/Resources/*Resource.php`. Filament v5 tables take a single base query via `->query(Closure)`, and **that same query object is reused for every table-driven operation**, not just rendering — including bulk actions and `->reorderable()`'s drag-and-drop `UPDATE ... SET order = case ... end WHERE id IN (...)`. Any `leftJoin`/`groupBy`/custom `select` added to that base query purely for a display column (e.g. an aggregate) will leak into unrelated write statements against the model's own table.

Prefer relationship-based aggregates (`withSum`/`withCount`, which compile to a subquery) or `whereHas` over manual joins in a resource's `->query()` closure — see `ProductResource::table()` for the pattern (it computes `order_items_sum_quantity` via `withSum('orderItems', 'quantity')`, and its date-range filter uses nested `whereHas` instead of joining `orders`/`order_items` onto `products`).

### Health checks

`spatie/laravel-health` is wired up in `app/Providers/HealthServiceProvider.php`. The checks that matter here are `DatabaseCheck`, `RedisCheck` and `HorizonCheck` — during a festival the panel is the only till, so those three failing means sales stop. `DebugModeCheck`, `EnvironmentCheck` and `OptimizedAppCheck` are registered **only in production**, since they fail by definition locally.

The results are served at `/health` (Blade) and `/health/json`, both behind `Authenticate` + `EnsureUserIsAdmin` — the page lists disk usage, Horizon state and packages with known advisories, so it must not be public or visible to cashiers. `routes/console.php` schedules `health:check` and `health:schedule-check-heartbeat` every minute; without the heartbeat `ScheduleCheck` correctly reports that cron is not running.

Note `REDIS_HOST` must be `redis` (the Sail service name), not `127.0.0.1` — with localhost the app cannot reach Redis and **Horizon will not start at all**. This stayed hidden for a long time because the queue, cache and session drivers are all `database`.

### i18n

All user-facing labels go through `__('filament.xxx')`, with translations in `lang/it` (primary) and `lang/en`.
