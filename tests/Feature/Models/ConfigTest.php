<?php

use App\Models\Config;
use Illuminate\Database\QueryException;

beforeEach(function () {
    // la memoizzazione è statica e sopravvive al rollback di RefreshDatabase:
    // ogni test parte da una cache pulita
    Config::flushValueCache();
});

afterEach(function () {
    Config::flushValueCache();
});

it('legge il valore di configurazione dal database', function () {
    // le migration eseguono DatabaseSeeder, quindi la riga max_qty esiste già:
    // setConfig() fa updateOrCreate e non viola il vincolo UNIQUE su code
    setConfig('max_qty', '12');

    expect(Config::value('max_qty'))->toBe('12');
});

it('restituisce il default quando il codice non esiste', function () {
    expect(Config::value('codice_inesistente', 'fallback'))->toBe('fallback')
        ->and(Config::value('altro_inesistente'))->toBeNull();
});

it('legge una sola volta lo stesso codice nello stesso processo', function () {
    setConfig('timezone', 'Europe/Rome');

    $query = countQueries(function () {
        Config::value('timezone');
        Config::value('timezone');
        Config::value('timezone');
    });

    expect($query)->toBe(1);
});

it('memoizza anche l assenza del codice senza rileggere', function () {
    $query = countQueries(function () {
        Config::value('mai_definito', 'x');
        Config::value('mai_definito', 'x');
    });

    expect($query)->toBe(1);
});

it('esegue una query per ciascun codice diverso', function () {
    Config::factory()->of('primo', 'a')->create();
    Config::factory()->of('secondo', 'b')->create();
    Config::flushValueCache();

    $query = countQueries(function () {
        Config::value('primo');
        Config::value('secondo');
        Config::value('primo');
        Config::value('secondo');
    });

    expect($query)->toBe(2);
});

it('flushValueCache svuota la memoizzazione', function () {
    setConfig('max_qty', '12');
    Config::value('max_qty');

    Config::flushValueCache();

    expect(countQueries(fn () => Config::value('max_qty')))->toBe(1);
});

it('salvare una configurazione invalida la cache e la lettura vede il valore nuovo', function () {
    setConfig('max_qty', '12');
    expect(Config::value('max_qty'))->toBe('12');

    Config::whereCode('max_qty')->first()->update(['config_value' => '30']);

    // nessun flush manuale: ci pensa l'hook saved
    expect(Config::value('max_qty'))->toBe('30');
});

it('creare una configurazione invalida la cache di una lettura andata a vuoto', function () {
    expect(Config::value('nuovo_codice', 'default'))->toBe('default');

    Config::factory()->of('nuovo_codice', 'valorizzato')->create();

    expect(Config::value('nuovo_codice', 'default'))->toBe('valorizzato');
});

it('cancellare una configurazione invalida la cache e la lettura torna al default', function () {
    setConfig('max_qty', '12');
    expect(Config::value('max_qty', '5'))->toBe('12');

    Config::whereCode('max_qty')->first()->delete();

    // nessun flush manuale: ci pensa l'hook deleted
    expect(Config::value('max_qty', '5'))->toBe('5');
});

it('l helper setConfig aggiorna il valore visibile alla lettura successiva', function () {
    setConfig('max_qty', '10');
    expect(Config::value('max_qty'))->toBe('10');

    setConfig('max_qty', '20');

    expect(Config::value('max_qty'))->toBe('20')
        ->and(Config::where('code', 'max_qty')->count())->toBe(1);
});

it('intValue converte i valori numerici', function () {
    setConfig('max_qty', '30');

    expect(Config::intValue('max_qty', 5))->toBe(30)->toBeInt();
});

it('intValue applica il default sui valori non numerici', function (string $grezzo, int $atteso) {
    setConfig('max_qty', $grezzo);

    expect(Config::intValue('max_qty', 5))->toBe($atteso);
})->with([
    // stringa vuota: (int) '' faceva 0 e azzerava le opzioni di quantità
    'stringa vuota' => ['', 5],
    'testo' => ['abc', 5],
    // numerici: passano, ma il minimo di default 1 fa da pavimento
    'zero' => ['0', 1],
    'negativo' => ['-5', 1],
    'valido' => ['30', 30],
]);

it('intValue non scende mai sotto il minimo richiesto', function () {
    setConfig('max_qty', '2');

    expect(Config::intValue('max_qty', 5, 10))->toBe(10)
        ->and(Config::intValue('max_qty', 5, 1))->toBe(2)
        ->and(Config::intValue('max_qty', 5, 0))->toBe(2);
});

it('intValue applica il minimo anche al default', function () {
    // codice assente: si usa il default, che a sua volta è limitato dal minimo
    expect(Config::intValue('codice_assente', 3, 10))->toBe(10)
        ->and(Config::intValue('codice_assente', 0))->toBe(1);
});

it('intValue ignora il valore memoizzato di un altro codice', function () {
    setConfig('max_qty', '30');
    setConfig('altro_codice', 'abc');

    expect(Config::intValue('max_qty', 5))->toBe(30)
        ->and(Config::intValue('altro_codice', 5))->toBe(5);
});

it('il codice di configurazione e unico a livello di database', function () {
    Config::factory()->of('codice_unico_test', '12')->create();

    // il vincolo UNIQUE su configs.code rende impossibile avere due righe con
    // lo stesso codice, quindi l'orderBy('id') di value() non è più
    // osservabile: non esistono più duplicati da disambiguare
    expect(fn () => Config::factory()->of('codice_unico_test', '30')->create())
        ->toThrow(QueryException::class);
});
