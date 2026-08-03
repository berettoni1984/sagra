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
sail test                              # run full test suite (PHPUnit runner)
sail artisan test --filter=SomeTest    # run a single test
sail bin phpstan analyse                # static analysis (Larastan, level 8, app/ only — see phpstan.neon)
sail bin phpmd app text phpmdruleset.xml   # mess detector (excludes tests/, storage/)
sail bin pint                          # code style (Laravel Pint)
```

Note: `pestphp/pest` is a dev dependency but the existing tests (`tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php`) are plain PHPUnit `TestCase` classes — there is no real test coverage of app logic yet, so don't assume behavior is pinned down by tests.

## Architecture

### Domain model

- `Queue` — an independent ordering line ("Fila 1", "Fila 2", ...). Holds its own `order_number` counter, incremented per created order, so numbering is per-queue, not global. `Product`s are assigned to queues via a `product_queue` pivot (many-to-many), so different lines can sell different subsets of products.
- `Order` → `hasMany` `OrderItem`. `OrderItem` snapshots product name/price/quantity/amount at creation time (not a live reference), and belongs to `Product` (nullable — product can later be deleted).
- `Product` ↔ `Ingredient` many-to-many via `product_ingredient` pivot with a `qty` column (how much of that ingredient one unit of the product consumes).
- `Config` is a key/value settings table used for the app timezone, `max_qty`, and toggles like `free` (no-payment) and `change_price`. **Read it via `Config::value('code')` / `Config::intValue('code', $default)`**, never `Config::whereCode(...)->first()->config_value` — the former memoizes per request/job (the raw form was being re-queried once per exported row and twice per repeater row) and `intValue` floors the result so a blank or non-numeric value can't collapse a loop bound to zero. The cache self-invalidates on save/delete and is flushed at the start of every queued job.
- `Order` and `OrderItem` use `SoftDeletes`; deleting an `Order` cascades to soft-delete its `orderItems` (see `Order::booted()`).

### Order creation flow

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

Both order-creation paths need this (`QuickCreateOrder::createOrder()` and `CreateOrder::mutateFormDataBeforeCreate()`/`afterCreate()`), plus `EditOrder`'s save and delete hooks.

### Importer: read cast values, not raw CSV keys

In `ProductImporter`, Filament runs `remapData()` then `castData()` *before* `resolveRecord()`, writing the cast value under the column's **canonical name** (`$this->data['backorder']`). Reading `$this->data[$this->columnMap['backorder']]` gets the untouched CSV string instead, where `(bool) 'FALSE'` is `true`. Also note `resolveRecord()` returning `null` makes Filament skip validation, fill, save *and* `afterSave()` for that row — which is why the create path syncs queues itself.

### Filament resources — query() is shared between read and write

Resources live in `app/Filament/Resources/*Resource.php`. Filament v5 tables take a single base query via `->query(Closure)`, and **that same query object is reused for every table-driven operation**, not just rendering — including bulk actions and `->reorderable()`'s drag-and-drop `UPDATE ... SET order = case ... end WHERE id IN (...)`. Any `leftJoin`/`groupBy`/custom `select` added to that base query purely for a display column (e.g. an aggregate) will leak into unrelated write statements against the model's own table.

Prefer relationship-based aggregates (`withSum`/`withCount`, which compile to a subquery) or `whereHas` over manual joins in a resource's `->query()` closure — see `ProductResource::table()` for the pattern (it computes `order_items_sum_quantity` via `withSum('orderItems', 'quantity')`, and its date-range filter uses nested `whereHas` instead of joining `orders`/`order_items` onto `products`).

### i18n

All user-facing labels go through `__('filament.xxx')`, with translations in `lang/it` (primary) and `lang/en`.
