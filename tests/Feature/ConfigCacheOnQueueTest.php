<?php

use App\Models\Config;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ReadConfigJob;

beforeEach(function () {
    ReadConfigJob::$seen = null;
    Config::flushValueCache();
});

it('un job in coda non vede un valore di configurazione stantio', function () {
    setConfig('name', 'Sagra Vecchia');

    // scalda la cache nel processo corrente
    expect(Config::value('name'))->toBe('Sagra Vecchia');

    // modifica che scavalca il model, quindi senza invalidazione automatica:
    // e' il caso di un'altra richiesta o di un altro processo che cambia il valore
    DB::table('configs')->where('code', 'name')->update(['config_value' => 'Sagra Nuova']);

    // il processo corrente resta legittimamente sul valore memoizzato
    expect(Config::value('name'))->toBe('Sagra Vecchia');

    // il job invece riparte pulito, grazie al listener su JobProcessing
    ReadConfigJob::dispatch('name');

    expect(ReadConfigJob::$seen)->toBe('Sagra Nuova');
});

it('salvare una configurazione invalida subito la cache nello stesso processo', function () {
    setConfig('name', 'Prima');
    expect(Config::value('name'))->toBe('Prima');

    Config::whereCode('name')->first()->update(['config_value' => 'Dopo']);

    expect(Config::value('name'))->toBe('Dopo');
});

it('cancellare una configurazione invalida la cache', function () {
    setConfig('max_qty', '30');
    expect(Config::value('max_qty'))->toBe('30');

    Config::whereCode('max_qty')->first()->delete();

    expect(Config::value('max_qty'))->toBeNull()
        ->and(Config::intValue('max_qty', 15))->toBe(15);
});
