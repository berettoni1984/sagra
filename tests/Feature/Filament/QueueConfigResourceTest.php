<?php

use App\Filament\Resources\ConfigResource\Pages\CreateConfig;
use App\Filament\Resources\ConfigResource\Pages\EditConfig;
use App\Filament\Resources\ConfigResource\Pages\ListConfigs;
use App\Filament\Resources\QueueResource\Pages\CreateQueue;
use App\Filament\Resources\QueueResource\Pages\EditQueue;
use App\Filament\Resources\QueueResource\Pages\ListQueues;
use App\Models\Config;
use App\Models\Order;
use App\Models\Queue;
use Livewire\Livewire;

beforeEach(function () {
    actingAsAdmin();
});

it('elenca le code', function () {
    $code = Queue::factory()->count(2)->create();

    Livewire::test(ListQueues::class)->assertCanSeeTableRecords($code);
});

it('elenca le code ordinate per la colonna order', function () {
    Queue::query()->delete();
    $terza = Queue::factory()->atOrder(3)->create();
    $prima = Queue::factory()->atOrder(1)->create();
    $seconda = Queue::factory()->atOrder(2)->create();

    Livewire::test(ListQueues::class)
        ->assertCanSeeTableRecords([$prima, $seconda, $terza], inOrder: true);
});

it('riordinando aggiorna la colonna order delle code', function () {
    Queue::query()->delete();
    $a = Queue::factory()->atOrder(1)->create();
    $b = Queue::factory()->atOrder(2)->create();
    $c = Queue::factory()->atOrder(3)->create();

    Livewire::test(ListQueues::class)->call('reorderTable', [$c->id, $a->id, $b->id]);

    expect($c->fresh()->order)->toBe(1)
        ->and($a->fresh()->order)->toBe(2)
        ->and($b->fresh()->order)->toBe(3);
});

it('la coda predefinita e la prima abilitata in ordine di tabella', function () {
    Queue::query()->delete();
    $disabilitata = Queue::factory()->disabled()->atOrder(1)->create();
    $prima = Queue::factory()->atOrder(2)->create();
    Queue::factory()->atOrder(3)->create();

    // la disabilitata ha la posizione più bassa ma non è selezionabile in cassa
    expect(Queue::defaultQueue()?->id)->toBe($prima->id)
        ->and($disabilitata->fresh())->not->toBeNull();
});

it('a parita di posizione la coda predefinita e quella creata prima', function () {
    Queue::query()->delete();
    $prima = Queue::factory()->atOrder(0)->create();
    Queue::factory()->atOrder(0)->create();

    expect(Queue::defaultQueue()?->id)->toBe($prima->id);
});

it('crea una coda dal form', function () {
    Livewire::test(CreateQueue::class)
        ->fillForm(['name' => 'GRIGLIA', 'comment' => 'griglia', 'order_number' => 0])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Queue::firstWhere('comment', 'griglia'))->not->toBeNull();
});

it('azzera la numerazione di una singola coda lasciando intatte le altre', function () {
    $bersaglio = Queue::factory()->create(['order_number' => 55]);
    $altra = Queue::factory()->create(['order_number' => 33]);

    Livewire::test(ListQueues::class)->callTableAction('resetNumber', $bersaglio);

    expect($bersaglio->fresh()->order_number)->toBe(0)
        ->and($bersaglio->fresh()->reset_at)->not->toBeNull()
        // il reset per singola coda non deve toccare le altre file
        ->and($altra->fresh()->order_number)->toBe(33)
        ->and($altra->fresh()->reset_at)->toBeNull();
});

it('modifica una coda mai azzerata senza pretendere una data di reset', function () {
    // reset_at e' nullable e NULL significa "mai azzerata": quando il campo era
    // obbligatorio non si poteva rinominare una coda in questo stato
    $coda = Queue::factory()->create(['name' => 'VECCHIO', 'reset_at' => null]);

    Livewire::test(EditQueue::class, ['record' => $coda->getKey()])
        ->fillForm(['name' => 'NUOVO'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($coda->fresh()->name)->toBe('NUOVO')
        ->and($coda->fresh()->reset_at)->toBeNull();
});

it('modifica una coda conservando la data di azzeramento esistente', function () {
    $coda = Queue::factory()->resetAt('2026-08-01 10:00:00')->create(['name' => 'VECCHIO']);

    Livewire::test(EditQueue::class, ['record' => $coda->getKey()])
        ->fillForm(['name' => 'NUOVO'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($coda->fresh()->name)->toBe('NUOVO')
        ->and($coda->fresh()->reset_at->format('Y-m-d H:i'))->toBe('2026-08-01 10:00');
});

it('cancellare una coda con ordini non fallisce e non perde gli ordini', function () {
    $coda = Queue::factory()->create();
    $ordine = Order::factory()->create(['queue_id' => $coda->id]);

    Livewire::test(EditQueue::class, ['record' => $coda->getKey()])->callAction('delete');

    // la FK e' ON DELETE SET NULL: prima era RESTRICT e dava errore 1451
    expect(Queue::find($coda->id))->toBeNull()
        ->and($ordine->fresh())->not->toBeNull()
        ->and($ordine->fresh()->queue_id)->toBeNull();
});

it('elenca le configurazioni seminate dalle migration', function () {
    Livewire::test(ListConfigs::class)
        ->assertSuccessful()
        ->assertSee('max_qty');
});

it('richiede il valore della configurazione', function () {
    // configs.config_value e' NOT NULL e Filament converte l'input vuoto in
    // null: senza required() il salvataggio finiva in un errore SQL 500
    Livewire::test(CreateConfig::class)
        ->fillForm(['code' => 'nuovo_codice', 'config_value' => null])
        ->call('create')
        ->assertHasFormErrors(['config_value']);
});

it('richiede il codice della configurazione', function () {
    Livewire::test(CreateConfig::class)
        ->fillForm(['code' => null, 'config_value' => 'x'])
        ->call('create')
        ->assertHasFormErrors(['code']);
});

it('crea una configurazione valida', function () {
    Livewire::test(CreateConfig::class)
        ->fillForm(['code' => 'codice_nuovo', 'config_value' => 'valore'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Config::value('codice_nuovo'))->toBe('valore');
});

it('non permette di svuotare il valore di una configurazione esistente', function () {
    $config = Config::whereCode('max_qty')->first();

    Livewire::test(EditConfig::class, ['record' => $config->getKey()])
        ->fillForm(['config_value' => null])
        ->call('save')
        ->assertHasFormErrors(['config_value']);
});

it('modificando una configurazione la lettura successiva vede il valore nuovo', function () {
    $config = Config::whereCode('max_qty')->first();

    Livewire::test(EditConfig::class, ['record' => $config->getKey()])
        ->fillForm(['config_value' => '42'])
        ->call('save')
        ->assertHasNoFormErrors();

    // l'hook saved svuota la memoizzazione
    expect(Config::value('max_qty'))->toBe('42')
        ->and(Config::intValue('max_qty', 15))->toBe(42);
});

it('nega alla cassa l accesso alle configurazioni', function () {
    actingAsCassa();

    $this->get('/configs')->assertForbidden();
});
