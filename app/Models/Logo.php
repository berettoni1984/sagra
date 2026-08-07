<?php

namespace App\Models;

use Database\Factories\LogoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $path
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Database\Factories\LogoFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Logo extends Model
{
    /** @use HasFactory<LogoFactory> */
    use HasFactory;

    /**
     * {@inheritDoc}
     */
    protected $fillable = [
        'path',
        'order',
    ];

    /**
     * {@inheritDoc}
     */
    protected $casts = [
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * I loghi nell'ordine deciso dalla colonna `order` (l'id fa da spareggio
     * quando due loghi condividono la stessa posizione).
     *
     * @return Builder<Logo>
     */
    public static function ordered(): Builder
    {
        return self::query()
            ->orderBy('order')
            ->orderBy('id');
    }

    /**
     * Il logo stampato sugli scontrini: non c'e' un flag, e' semplicemente il
     * primo logo secondo l'ordinamento della tabella.
     */
    public static function defaultLogo(): ?self
    {
        return self::ordered()->first();
    }
}
