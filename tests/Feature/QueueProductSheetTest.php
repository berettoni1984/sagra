<?php

use App\Filament\Resources\QueueResource\Pages\ListQueues;
use App\Models\Product;
use App\Models\Queue;
use App\Services\QueueProductSheet;
use Livewire\Livewire;

beforeEach(function () {
    actingAsAdmin();
});

/** Legge un file dentro l'xlsx: il formato è uno zip di XML. */
function xmlDelFoglio(string $path, string $interno): string
{
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $contenuto = $zip->getFromName($interno);
    $zip->close();

    expect($contenuto)->not->toBeFalse();

    return (string) $contenuto;
}

function scriviFoglio(Queue $coda): string
{
    $path = tempnam(sys_get_temp_dir(), 'listino').'.xlsx';
    app(QueueProductSheet::class)->write($coda, $path);

    return $path;
}

it('scrive l intestazione nome prodotto, prezzo e quantita', function () {
    $coda = Queue::factory()->create();
    $coda->products()->attach(Product::factory()->create(['name' => 'PANINO'])->id);

    $xml = xmlDelFoglio(scriviFoglio($coda), 'xl/worksheets/sheet1.xml');

    expect($xml)->toContain(__('filament.Product Name'))
        ->and($xml)->toContain(__('filament.Price'))
        ->and($xml)->toContain(__('filament.Qty'))
        ->and($xml)->toContain('PANINO');
});

it('stampa il prezzo del prodotto come numero', function () {
    $coda = Queue::factory()->create();
    $coda->products()->attach(Product::factory()->create(['name' => 'PIADINA', 'price' => '4.50'])->id);

    $xml = xmlDelFoglio(scriviFoglio($coda), 'xl/worksheets/sheet1.xml');

    // riga 2 = primo prodotto: B2 porta il valore numerico, non una stringa
    expect($xml)->toMatch('/<c r="B2" s="\d+"><v>4\.5<\/v><\/c>/');
});

it('lascia vuota la cella della quantita ma la scrive con uno stile', function () {
    $coda = Queue::factory()->create();
    $coda->products()->attach(Product::factory()->create(['name' => 'PIADINA'])->id);

    $xml = xmlDelFoglio(scriviFoglio($coda), 'xl/worksheets/sheet1.xml');

    // C2 esiste (ha il bordo) ma non ha valore: è lo spazio da riempire a mano
    expect($xml)->toMatch('/<c r="C2" s="\d+"\/>/');
});

it('imposta il foglio su A4 verticale', function () {
    $coda = Queue::factory()->create();
    $coda->products()->attach(Product::factory()->create()->id);

    $xml = xmlDelFoglio(scriviFoglio($coda), 'xl/worksheets/sheet1.xml');

    // paperSize 9 è l'A4 nel formato xlsx
    expect($xml)->toContain('paperSize="9"')
        ->and($xml)->toContain('orientation="portrait"');
});

it('applica i bordi a tutte le celle della tabella', function () {
    $coda = Queue::factory()->create();
    $coda->products()->attach(Product::factory()->count(2)->create()->pluck('id'));

    $path = scriviFoglio($coda);
    $stili = xmlDelFoglio($path, 'xl/styles.xml');
    $foglio = xmlDelFoglio($path, 'xl/worksheets/sheet1.xml');

    // un solo bordo definito, i quattro lati sottili, usato da header e righe
    expect($stili)->toContain('<borders count="2">')
        ->and(substr_count($stili, '<top style="thin">'))->toBe(1)
        ->and(substr_count($stili, '<bottom style="thin">'))->toBe(1)
        // 3 righe (header + 2 prodotti) x 3 colonne, tutte con uno stile
        ->and(preg_match_all('/<c r="[ABC]\d+" s="\d+"/', $foglio))->toBe(9);
});

it('elenca i prodotti della coda in ordine di listino, esclusi i disabilitati', function () {
    $coda = Queue::factory()->create();
    $secondo = Product::factory()->create(['name' => 'SECONDO', 'order' => 2]);
    $primo = Product::factory()->create(['name' => 'PRIMO', 'order' => 1]);
    $spento = Product::factory()->disabled()->create(['name' => 'SPENTO', 'order' => 3]);
    $altraCoda = Product::factory()->create(['name' => 'ALTRA CODA', 'order' => 4]);
    $coda->products()->attach([$secondo->id, $primo->id, $spento->id]);
    Queue::factory()->create()->products()->attach($altraCoda->id);

    $nomi = app(QueueProductSheet::class)->products($coda)->pluck('name')->all();

    expect($nomi)->toBe(['PRIMO', 'SECONDO']);
});

it('l action della tabella restituisce il download del listino', function () {
    $coda = Queue::factory()->create(['name' => 'Fila', 'comment' => '1']);
    $coda->products()->attach(Product::factory()->create()->id);

    Livewire::test(ListQueues::class)
        ->callTableAction('productSheet', $coda)
        ->assertFileDownloaded(app(QueueProductSheet::class)->fileName($coda));
});

it('avvisa senza scaricare nulla se la fila non ha prodotti attivi', function () {
    $coda = Queue::factory()->create();
    $coda->products()->attach(Product::factory()->disabled()->create()->id);

    Livewire::test(ListQueues::class)
        ->callTableAction('productSheet', $coda)
        ->assertNotified()
        ->assertNoFileDownloaded();
});
