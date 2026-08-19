<?php

use App\Filament\Imports\ProductImporter;
use App\Models\Product;
use App\Models\Queue;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;

/**
 * L'importer viene invocato una riga alla volta da Filament, dopo aver
 * rimappato e castato i dati. Qui lo si pilota direttamente per coprire i
 * casi che causavano perdita di dati.
 */
function importer(array $columnMap): ProductImporter
{
    $import = Import::create([
        'file_name' => 'prodotti.csv',
        'file_path' => 'imports/prodotti.csv',
        'importer' => ProductImporter::class,
        'processed_rows' => 0,
        'total_rows' => 1,
        'successful_rows' => 0,
        'user_id' => admin()->id,
    ]);

    return new ProductImporter($import, $columnMap, []);
}

/** Mappa identita': gli header del CSV coincidono coi nomi delle colonne. */
function mappaIdentita(): array
{
    return [
        'name' => 'name', 'price' => 'price', 'stock' => 'stock',
        'backorder' => 'backorder', 'is_disabled' => 'is_disabled',
        'queues' => 'queues', 'order' => 'order',
    ];
}

it('crea un prodotto nuovo dalla riga del csv', function () {
    importer(mappaIdentita())(['name' => 'PANINO', 'price' => '4.50', 'stock' => '20']);

    $prodotto = Product::firstWhere('name', 'PANINO');

    expect($prodotto)->not->toBeNull()
        ->and($prodotto->price)->toBe('4.50')
        ->and($prodotto->stock)->toBe(20);
});

it('non crea il prodotto disabilitato quando la colonna non e mappata', function () {
    // il default era true: un listino name;price creava tutto il catalogo
    // disattivato e invisibile in cassa
    importer(['name' => 'name', 'price' => 'price'])(['name' => 'PIADINA', 'price' => '3.00']);

    expect(Product::firstWhere('name', 'PIADINA')->is_disabled)->toBeFalsy();
});

it('interpreta correttamente i booleani testuali del csv', function (string $grezzo, bool $atteso) {
    // (bool) 'FALSE' vale true in PHP: leggendo il valore grezzo invece di
    // quello castato i prodotti nascevano in backorder, disattivando ogni
    // controllo di giacenza
    $importer = importer(mappaIdentita());
    $importer(['name' => 'PROD '.$grezzo, 'price' => '1.00', 'backorder' => $grezzo]);

    expect((bool) Product::firstWhere('name', 'PROD '.$grezzo)->backorder)->toBe($atteso);
})->with([
    'false minuscolo' => ['false', false],
    'no' => ['no', false],
    'zero' => ['0', false],
    'off' => ['off', false],
    'true' => ['true', true],
    'uno' => ['1', true],
    'yes' => ['yes', true],
]);

it('non solleva errori quando la colonna queues non e mappata', function () {
    // explode() su un valore non stringa lanciava un TypeError: la riga
    // risultava fallita pur essendo gia' stata salvata
    $prodotto = Product::factory()->create(['price' => '1.00']);
    $coda = Queue::factory()->create(['comment' => 'pizza']);
    $prodotto->queues()->attach($coda);

    importer(['name' => 'name', 'price' => 'price'])(
        ['name' => $prodotto->name, 'price' => '7.77']
    );

    expect($prodotto->fresh()->price)->toBe('7.77')
        // e le associazioni alle code non vengono toccate
        ->and($prodotto->fresh()->queues)->toHaveCount(1);
});

it('non stacca il prodotto da tutte le code quando la cella queues e vuota', function () {
    // sync([]) faceva sparire il prodotto da ogni fila del POS, senza avvisi
    $prodotto = Product::factory()->create(['price' => '1.00']);
    $prodotto->queues()->attach(Queue::factory()->count(2)->create()->pluck('id'));

    importer(mappaIdentita())(['name' => $prodotto->name, 'price' => '5.00', 'queues' => '']);

    expect($prodotto->fresh()->queues)->toHaveCount(2);
});

it('non stacca il prodotto se nessuna coda corrisponde al valore indicato', function () {
    $prodotto = Product::factory()->create(['price' => '1.00']);
    $prodotto->queues()->attach(Queue::factory()->create()->id);

    importer(mappaIdentita())(['name' => $prodotto->name, 'price' => '5.00', 'queues' => 'inesistente']);

    expect($prodotto->fresh()->queues)->toHaveCount(1);
});

it('associa le code elencate nella cella, riconoscendole dal commento', function () {
    $prodotto = Product::factory()->create(['price' => '1.00']);
    $pizza = Queue::factory()->create(['comment' => 'pizza']);
    $griglia = Queue::factory()->create(['comment' => 'griglia']);

    importer(mappaIdentita())(
        ['name' => $prodotto->name, 'price' => '5.00', 'queues' => 'pizza, griglia']
    );

    expect($prodotto->fresh()->queues->pluck('comment')->sort()->values()->all())
        ->toBe(['griglia', 'pizza']);
});

it('sostituisce le code precedenti con quelle indicate', function () {
    $prodotto = Product::factory()->create(['price' => '1.00']);
    $vecchia = Queue::factory()->create(['comment' => 'vecchia']);
    $nuova = Queue::factory()->create(['comment' => 'nuova']);
    $prodotto->queues()->attach($vecchia);

    importer(mappaIdentita())(['name' => $prodotto->name, 'price' => '5.00', 'queues' => 'nuova']);

    expect($prodotto->fresh()->queues->pluck('comment')->all())->toBe(['nuova']);
});

it('assegna una posizione di ordinamento progressiva ai nuovi prodotti', function () {
    Product::factory()->create(['order' => 7]);

    importer(mappaIdentita())(['name' => 'NUOVO', 'price' => '1.00']);

    expect(Product::firstWhere('name', 'NUOVO')->order)->toBe(8);
});

it('rispetta la posizione di ordinamento indicata nel csv', function () {
    importer(mappaIdentita())(['name' => 'NUOVO', 'price' => '1.00', 'order' => '3']);

    expect(Product::firstWhere('name', 'NUOVO')->order)->toBe(3);
});

it('fa fallire la riga senza nome invece di contarla fra le importate', function () {
    // restituendo null Filament salta validazione, fill e save ma incrementa
    // successful_rows: la riga scompariva senza alcun segnale
    $prima = Product::count();

    expect(fn () => importer(mappaIdentita())(['name' => '', 'price' => '1.00']))
        ->toThrow(RowImportFailedException::class);

    expect(Product::count())->toBe($prima);
});

it('fa fallire la riga il cui nome e composto solo da spazi', function () {
    expect(fn () => importer(mappaIdentita())(['name' => "  \u{00A0} ", 'price' => '1.00']))
        ->toThrow(RowImportFailedException::class);
});

it('aggiorna il prodotto esistente riconoscendolo dal nome', function () {
    // prima l'id del CSV vinceva sul nome: un id proveniente da un altro
    // ambiente aggiornava il prodotto sbagliato o duplicava il listino
    $prodotto = Product::factory()->create(['name' => 'PANINO', 'price' => '1.00']);

    importer(mappaIdentita())(['name' => 'PANINO', 'price' => '6.50', 'queues' => '']);

    expect(Product::where('name', 'PANINO')->count())->toBe(1)
        ->and($prodotto->fresh()->price)->toBe('6.50');
});

it('ritrova il prodotto ignorando maiuscole e minuscole', function () {
    $prodotto = Product::factory()->create(['name' => 'Panino', 'price' => '1.00']);

    importer(mappaIdentita())(['name' => 'PANINO', 'price' => '2.20', 'queues' => '']);

    expect(Product::count())->toBe(1)
        ->and($prodotto->fresh()->price)->toBe('2.20')
        // il listino del CSV fa da riferimento anche per le maiuscole
        ->and($prodotto->fresh()->name)->toBe('PANINO');
});

it('ritrova il prodotto ignorando gli spazi ai bordi', function () {
    $prodotto = Product::factory()->create(['name' => 'Piadina', 'price' => '1.00']);

    importer(mappaIdentita())(['name' => "  piadina\u{00A0}", 'price' => '3.30', 'queues' => '']);

    expect(Product::count())->toBe(1)
        ->and($prodotto->fresh()->price)->toBe('3.30')
        ->and($prodotto->fresh()->name)->toBe('piadina');
});

it('due righe con lo stesso nome scritto in modo diverso aggiornano un solo prodotto', function () {
    importer(mappaIdentita())(['name' => 'Crescione', 'price' => '2.00']);
    importer(mappaIdentita())(['name' => ' CRESCIONE ', 'price' => '2.50']);

    expect(Product::count())->toBe(1)
        ->and(Product::first()->price)->toBe('2.50');
});

it('salva il nome ripulito dagli spazi anche sui prodotti nuovi', function () {
    importer(mappaIdentita())(['name' => '  SALSICCIA  ', 'price' => '1.00']);

    expect(Product::firstWhere('name', 'SALSICCIA'))->not->toBeNull();
});

it('rifiuta un nome piu lungo del limite della colonna', function () {
    expect(fn () => importer(mappaIdentita())(
        ['name' => str_repeat('a', 256), 'price' => '1.00']
    ))->toThrow(ValidationException::class);

    expect(Product::count())->toBe(0);
});

it('rifiuta con un errore di validazione una giacenza fuori dal range smallint', function () {
    // products.stock e' smallint: prima il database rispondeva con un errore
    // grezzo 22003 invece di una violazione di validazione leggibile
    expect(fn () => importer(mappaIdentita())(
        ['name' => 'ESAGERATO', 'price' => '1.00', 'stock' => '40000']
    ))->toThrow(ValidationException::class);

    expect(Product::firstWhere('name', 'ESAGERATO'))->toBeNull();
});

it('normalizza a zero un prezzo non numerico invece di andare in errore', function () {
    // il cast ->numeric() di Filament trasforma 'abc' in 0 prima della
    // validazione, quindi la riga passa e il prodotto nasce a prezzo zero
    importer(mappaIdentita())(['name' => 'PREZZO ROTTO', 'price' => 'abc']);

    expect(Product::firstWhere('name', 'PREZZO ROTTO')->price)->toBe('0.00');
});

it('non fallisce con la cella prezzo vuota', function () {
    // '' viene castato a NULL: products.price e' NOT NULL, quindi il default
    // del model deve reggere
    importer(mappaIdentita())(['name' => 'PREZZO VUOTO', 'price' => '']);

    expect(Product::firstWhere('name', 'PREZZO VUOTO'))->not->toBeNull();
});

it('il separatore decimale deve essere il punto, non la virgola', function () {
    // ATTENZIONE: il cast ->numeric() rimuove la virgola, quindi '12,50'
    // diventa 1250. L'export del progetto scrive il punto, quindi un
    // andata-e-ritorno e' sicuro; il rischio e' la digitazione manuale.
    importer(mappaIdentita())(['name' => 'CON VIRGOLA', 'price' => '12,50']);

    expect(Product::firstWhere('name', 'CON VIRGOLA')->price)->toBe('1250.00');
});

it('rifiuta un prezzo negativo', function () {
    expect(fn () => importer(mappaIdentita())(
        ['name' => 'PREZZO NEGATIVO', 'price' => '-5']
    ))->toThrow(ValidationException::class);
});

it('crea il prodotto a prezzo zero se la colonna prezzo non e mappata', function () {
    // products.price e' NOT NULL senza default
    importer(['name' => 'name'])(['name' => 'SENZA PREZZO']);

    expect(Product::firstWhere('name', 'SENZA PREZZO')->price)->toBe('0.00');
});

it('un import di solo aggiornamento non richiede il prezzo', function () {
    $prodotto = Product::factory()->create(['stock' => 5]);

    importer(['name' => 'name', 'stock' => 'stock'])(
        ['name' => $prodotto->name, 'stock' => '77']
    );

    expect($prodotto->fresh()->stock)->toBe(77);
});

it('collega le code anche ai prodotti appena creati', function () {
    // afterSave() prima non girava per i nuovi record, perche' resolveRecord()
    // restituiva null e Filament saltava validazione, fill, save e hook
    $coda = Queue::factory()->create(['comment' => 'pizza']);

    importer(mappaIdentita())(['name' => 'NUOVO CON CODA', 'price' => '2.00', 'queues' => 'pizza']);

    expect(Product::firstWhere('name', 'NUOVO CON CODA')->queues->pluck('comment')->all())
        ->toBe(['pizza']);
});
