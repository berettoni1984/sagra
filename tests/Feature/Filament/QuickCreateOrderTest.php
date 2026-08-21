<?php

use App\Filament\Resources\OrderResource\Pages\QuickCreateOrder;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Queue;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Cassa rapida: è il percorso dove passa il denaro, quindi i test coprono
 * numerazione, scarico giacenze e importi, non solo il rendering.
 */
beforeEach(function () {
    $this->user = actingAsAdmin();
    $this->queue = Queue::factory()->create(['order_number' => 41]);
    $this->product = Product::factory()->create(['price' => '2.50', 'stock' => 10]);
    $this->product->queues()->attach($this->queue);
});

function quickCreate(?Queue $queue = null)
{
    $component = Livewire::test(QuickCreateOrder::class);

    return $queue ? $component->set('queueId', $queue->id) : $component;
}

it('mostra solo i prodotti della coda selezionata e non quelli disabilitati', function () {
    $altraCoda = Queue::factory()->create();
    $altrove = Product::factory()->create();
    $altrove->queues()->attach($altraCoda);

    $disabilitato = Product::factory()->disabled()->create();
    $disabilitato->queues()->attach($this->queue);

    $ids = collect(quickCreate($this->queue)->instance()->getProducts())->pluck('id');

    expect($ids)->toContain($this->product->id)
        ->and($ids)->not->toContain($altrove->id)
        ->and($ids)->not->toContain($disabilitato->id);
});

it('preseleziona la prima coda abilitata in ordine di tabella', function () {
    Queue::query()->delete();
    Queue::factory()->disabled()->atOrder(1)->create();
    $attesa = Queue::factory()->atOrder(2)->create();
    Queue::factory()->atOrder(3)->create();

    expect(Livewire::test(QuickCreateOrder::class)->get('queueId'))->toBe($attesa->id);
});

it('elenca le code nell ordine della tabella', function () {
    Queue::query()->delete();
    $seconda = Queue::factory()->atOrder(2)->create();
    $prima = Queue::factory()->atOrder(1)->create();

    $ids = collect(Livewire::test(QuickCreateOrder::class)->instance()->getQueues())->pluck('id');

    expect($ids->all())->toBe([$prima->id, $seconda->id]);
});

it('mostra le code come radio su una riga sola scorrevole', function () {
    $html = Livewire::test(QuickCreateOrder::class)->html();

    // niente select: pulsanti radio in orizzontale, senza andare a capo
    expect($html)->toContain('role="radiogroup"')
        ->and($html)->toContain('type="radio"')
        ->and($html)->toContain('flex-nowrap')
        ->and($html)->toContain('overflow-x-auto');
});

it('senza coda selezionata non elenca prodotti', function () {
    expect(Livewire::test(QuickCreateOrder::class)->set('queueId', null)->instance()->getProducts())->toBeEmpty();
});

it('aggiunge al carrello e calcola il totale', function () {
    $component = quickCreate($this->queue)
        ->call('addProduct', $this->product->id)
        ->call('addProduct', $this->product->id);

    expect($component->instance()->getTotalItemsCount())->toBe(2)
        ->and($component->instance()->getOrderTotal())->toBe(5.0);
});

it('crea l ordine incrementando il numero della coda e scaricando la giacenza', function () {
    quickCreate($this->queue)
        ->call('addProduct', $this->product->id)
        ->call('addProduct', $this->product->id)
        ->call('createOrder');

    $order = Order::latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->number)->toBe(42)
        ->and($order->queue_id)->toBe($this->queue->id)
        ->and($order->user_id)->toBe($this->user->id)
        ->and($order->total_amount)->toBe('5.00')
        ->and($order->orderItems)->toHaveCount(1)
        ->and($order->orderItems->first()->quantity)->toBe(2)
        // il nome è storicizzato sulla riga
        ->and($order->orderItems->first()->name)->toBe($this->product->name)
        ->and($this->queue->fresh()->order_number)->toBe(42)
        ->and($this->product->fresh()->stock)->toBe(8);
});

it('scarica anche la giacenza degli ingredienti in proporzione al pivot qty', function () {
    $ingrediente = Ingredient::factory()->create(['stock' => 100]);
    $spento = Ingredient::factory()->disabled()->create(['stock' => 100]);
    $this->product->ingredients()->attach($ingrediente, ['qty' => 3]);
    $this->product->ingredients()->attach($spento, ['qty' => 5]);

    quickCreate($this->queue)
        ->call('addProduct', $this->product->id)
        ->call('addProduct', $this->product->id)
        ->call('createOrder');

    expect($ingrediente->fresh()->stock)->toBe(94)   // 100 - (2 x 3)
        ->and($spento->fresh()->stock)->toBe(100);   // disabilitato: ignorato
});

it('non crea nulla se la coda non e selezionata', function () {
    $prima = Order::count();

    Livewire::test(QuickCreateOrder::class)->set('queueId', null)->call('createOrder');

    expect(Order::count())->toBe($prima)
        ->and($this->product->fresh()->stock)->toBe(10);
});

it('azzera carrello sconto e nota al cambio di coda', function () {
    $altraCoda = Queue::factory()->create();

    $component = quickCreate($this->queue)
        ->call('addProduct', $this->product->id)
        ->set('note', 'una nota')
        ->set('free', true)
        ->set('customTotalPaid', 3.0)
        ->set('queueId', $altraCoda->id);

    expect($component->get('items'))->toBeEmpty()
        ->and($component->get('note'))->toBeNull()
        ->and($component->get('free'))->toBeFalse()
        // lo sconto della coda precedente non deve sopravvivere
        ->and($component->get('customTotalPaid'))->toBeNull();
});

it('applica lo sconto manuale al totale pagato', function () {
    quickCreate($this->queue)
        ->call('addProduct', $this->product->id)
        ->set('customTotalPaid', 1.5)
        ->call('createOrder');

    expect(Order::latest('id')->first())
        ->total_amount->toBe('2.50')
        ->total_paid->toBe('1.50');
});

it('rifiuta un totale pagato negativo azzerandolo', function () {
    quickCreate($this->queue)
        ->call('addProduct', $this->product->id)
        ->set('customTotalPaid', -10.0)
        ->call('createOrder');

    expect(Order::latest('id')->first()->total_paid)->toBe('0.00');
});

it('con la modalita gratuita il totale pagato e zero', function () {
    quickCreate($this->queue)
        ->call('addProduct', $this->product->id)
        ->set('free', true)
        ->call('createOrder');

    expect(Order::latest('id')->first())
        ->total_amount->toBe('2.50')
        ->total_paid->toBe('0.00');
});

it('divide una riga mantenendo la quantita complessiva', function () {
    $component = quickCreate($this->queue)
        ->call('addProduct', $this->product->id)
        ->call('addProduct', $this->product->id)
        ->call('splitItem', 0);

    expect($component->get('items'))->toHaveCount(2)
        ->and($component->instance()->getTotalItemsCount())->toBe(2);

    $component->call('createOrder');

    // due righe distinte sull'ordine, una per pezzo
    expect(Order::latest('id')->first()->orderItems)->toHaveCount(2);
});

it('salva la nota di riga', function () {
    quickCreate($this->queue)
        ->call('addProduct', $this->product->id)
        ->call('updateItemNote', 0, 'senza cipolla')
        ->call('createOrder');

    expect(Order::latest('id')->first()->orderItems->first()->note)->toBe('senza cipolla');
});

it('segnala il carrello fuori stock ma consente comunque la vendita', function () {
    // comportamento voluto: la giacenza esaurita e' solo un avviso
    $scarso = Product::factory()->create(['stock' => 1, 'backorder' => false]);
    $scarso->queues()->attach($this->queue);

    $component = quickCreate($this->queue)
        ->call('addProduct', $scarso->id)
        ->call('addProduct', $scarso->id);

    expect($component->instance()->hasOutOfStockItems())->toBeTrue();

    $component->call('createOrder');

    expect(Order::latest('id')->first())->not->toBeNull()
        ->and($scarso->fresh()->stock)->toBe(-1);
});

it('un prodotto backorder non risulta mai fuori stock', function () {
    $illimitato = Product::factory()->backorder()->create(['stock' => 0]);
    $illimitato->queues()->attach($this->queue);

    $component = quickCreate($this->queue)->call('addProduct', $illimitato->id);
    $dati = collect($component->instance()->getProducts())->firstWhere('id', $illimitato->id);

    expect($dati['is_out_of_stock'])->toBeFalse()
        ->and($dati['backorder'])->toBeTrue()
        ->and($component->instance()->hasOutOfStockItems())->toBeFalse();
});

it('mostra il trattino invece della giacenza per i prodotti backorder', function () {
    $illimitato = Product::factory()->backorder()->create(['stock' => 999]);
    $illimitato->queues()->attach($this->queue);

    $html = quickCreate($this->queue)->html();
    $etichetta = __('filament.Stock');

    $cardBackorder = cardHtml($html, $illimitato->id);
    $cardTracciato = cardHtml($html, $this->product->id);

    expect($cardBackorder)->toContain($etichetta.': -')
        // nessun numero accanto all'etichetta per un backorder
        ->and($cardBackorder)->not->toMatch('/'.preg_quote($etichetta, '/').':\s*\d/')
        ->and($cardTracciato)->toContain($etichetta.': 10');
});

it('mostra i venduti della coda selezionata dal suo azzeramento', function () {
    $codaA = Queue::factory()->resetAt(Carbon::parse('2026-08-01 10:00'))->create();
    $codaB = Queue::factory()->create(['reset_at' => null]);
    $prodotto = Product::factory()->create();
    $prodotto->queues()->attach([$codaA->id, $codaB->id]);

    $vendita = function (Queue $coda, int $qty, string $quando) use ($prodotto) {
        $order = Order::factory()->create(['queue_id' => $coda->id]);
        $order->orderItems()->create([
            'product_id' => $prodotto->id, 'name' => $prodotto->name,
            'quantity' => $qty, 'amount' => '1.00', 'row_amount' => (string) $qty,
        ]);
        $order->forceFill(['created_at' => Carbon::parse($quando)])->saveQuietly();
    };

    $vendita($codaA, 3, '2026-08-01 12:00');   // conta
    $vendita($codaA, 50, '2026-07-20 12:00');  // prima del reset di A
    $vendita($codaB, 60, '2026-08-01 12:00');  // altra fila, mai azzerata

    $dati = collect(quickCreate($codaA)->instance()->getProducts())->firstWhere('id', $prodotto->id);

    expect($dati['sold'])->toBe(3);
});

it('conta tra i venduti l ordine battuto subito dopo l azzeramento', function () {
    // Giro completo cassa -> database -> cassa: con reset_at su un tipo di
    // colonna diverso da created_at l'ordine appena battuto risultava anteriore
    // all'azzeramento e i venduti restavano a 0.
    $this->queue->update(['reset_at' => now()]);

    quickCreate($this->queue)
        ->call('addProduct', $this->product->id)
        ->call('addProduct', $this->product->id)
        ->call('createOrder');

    $dati = collect(quickCreate($this->queue)->instance()->getProducts())
        ->firstWhere('id', $this->product->id);

    expect($dati['sold'])->toBe(2);
});

it('non conta tra i venduti gli ordini annullati', function () {
    $prodotto = Product::factory()->create();
    $prodotto->queues()->attach($this->queue);

    $order = Order::factory()->create(['queue_id' => $this->queue->id]);
    $order->orderItems()->create([
        'product_id' => $prodotto->id, 'name' => $prodotto->name,
        'quantity' => 9, 'amount' => '1.00', 'row_amount' => '9.00',
    ]);

    $venduti = fn () => collect(quickCreate($this->queue)->instance()->getProducts())
        ->firstWhere('id', $prodotto->id)['sold'];

    expect($venduti())->toBe(9);

    $order->delete();

    expect($venduti())->toBe(0);
});

it('non esegue una query per riga di carrello nel calcolo dei venduti', function () {
    $prodotti = Product::factory()->count(12)->create();
    $this->queue->products()->attach($prodotti->pluck('id'));

    $component = quickCreate($this->queue);
    foreach ($prodotti->take(8) as $p) {
        $component->call('addProduct', $p->id);
    }

    $queries = countQueries(fn () => $component->instance()->getProducts());

    // una manciata di query per l'intera griglia, non una per prodotto/riga
    expect($queries)->toBeLessThan(12);
});

/** Isola l'HTML della card di un prodotto nella griglia. */
function cardHtml(string $html, int $productId): string
{
    $start = strpos($html, 'addProduct('.$productId.')');
    expect($start)->not->toBeFalse();

    return substr($html, $start, strpos($html, '</button>', $start) - $start);
}
