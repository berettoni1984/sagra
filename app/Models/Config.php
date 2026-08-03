<?php

namespace App\Models;

use Database\Factories\ConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\Config
 *
 * @property int $id
 * @property string $code
 * @property string $config_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereConfigValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereUpdatedAt($value)
 *
 * @property string|null $comment
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereComment($value)
 *
 * @mixin \Eloquent
 */
class Config extends Model
{
    /** @use HasFactory<ConfigFactory> */
    use HasFactory;

    /**
     * {@inheritDoc}
     */
    protected $fillable = [
        'code',
        'config_value',
        'comment',
    ];

    /**
     * Valori già letti in questo processo.
     *
     * @var array<string, string|null>
     */
    private static array $valueCache = [];

    /**
     * Legge un valore di configurazione una sola volta per richiesta/job.
     *
     * Serviva perché diversi punti caldi rileggevano la stessa riga a ogni giro:
     * gli exporter una volta per riga esportata (decine di migliaia di query su
     * un export di fine sagra) e il repeater degli ordini due volte per ogni
     * riga di carrello a ogni ricalcolo.
     */
    public static function value(string $code, ?string $default = null): ?string
    {
        if (! array_key_exists($code, self::$valueCache)) {
            self::$valueCache[$code] = static::query()
                ->where('code', $code)
                ->orderBy('id')
                ->value('config_value');
        }

        return self::$valueCache[$code] ?? $default;
    }

    /**
     * Come value(), ma garantisce un intero >= $min.
     *
     * Un valore vuoto o non numerico dava (int) '' === 0: con max_qty a 0 il
     * ciclo delle quantità non produceva alcuna opzione e il form ordine
     * diventava inutilizzabile.
     */
    public static function intValue(string $code, int $default, int $min = 1): int
    {
        $raw = self::value($code);
        $value = is_numeric($raw) ? (int) $raw : $default;

        return max($value, $min);
    }

    public static function flushValueCache(): void
    {
        self::$valueCache = [];
    }

    protected static function booted(): void
    {
        // Una modifica dal pannello deve valere subito, senza aspettare la
        // richiesta successiva.
        static::saved(static fn () => self::flushValueCache());
        static::deleted(static fn () => self::flushValueCache());
    }
}
