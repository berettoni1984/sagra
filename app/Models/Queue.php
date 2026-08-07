<?php

namespace App\Models;

use Database\Factories\QueueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $comment
 * @property int $order_number
 * @property Carbon|null $reset_at
 * @property bool $is_disabled
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Database\Factories\QueueFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereIsDisabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereResetAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereUpdatedAt($value)
 *
 * @property-read Collection<int, Order> $orders
 * @property-read int|null $orders_count
 * @property-read string $label
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 *
 * @mixin \Eloquent
 */
class Queue extends Model
{
    /** @use HasFactory<QueueFactory> */
    use HasFactory;

    /**
     * {@inheritDoc}
     */
    protected $fillable = [
        'name',
        'comment',
        'order_number',
        'reset_at',
        'is_disabled',
        'order',
    ];

    /**
     * {@inheritDoc}
     */
    protected $casts = [
        'reset_at' => 'datetime',
        'is_disabled' => 'boolean',
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

    ];

    /**
     * Le code nell'ordine deciso dalla colonna `order` (l'id fa da spareggio
     * quando due code condividono la stessa posizione).
     *
     * @return Builder<Queue>
     */
    public static function ordered(): Builder
    {
        return self::query()
            ->orderBy('order')
            ->orderBy('id');
    }

    /**
     * @return Builder<Queue>
     */
    public static function enabledOrdered(): Builder
    {
        return self::ordered()->whereIsDisabled(false);
    }

    /**
     * La coda preselezionata in cassa: non c'e' un flag, e' semplicemente la
     * prima coda abilitata secondo l'ordinamento della tabella.
     */
    public static function defaultQueue(): ?self
    {
        return self::enabledOrdered()->first();
    }

    /**
     * @return HasMany<Order,$this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return BelongsToMany<Product,$this, Pivot>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * @return Attribute<string,string>
     */
    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->name.' '.$this->comment,
        );
    }
}
