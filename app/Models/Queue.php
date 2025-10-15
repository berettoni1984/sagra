<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property string $name
 * @property string|null $comment
 * @property int $order_number
 * @property \Illuminate\Support\Carbon|null $reset_at
 * @property bool $is_disabled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereResetAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereUpdatedAt($value)
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $orders
 * @property-read int|null $orders_count
 * @property-read string $label
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property bool $is_default
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Queue whereIsDefault($value)
 *
 * @mixin \Eloquent
 */
class Queue extends Model
{
    /** @use HasFactory<\Database\Factories\QueueFactory> */
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
        'is_default',
    ];

    /**
     * {@inheritDoc}
     */
    protected $casts = [
        'reset_at' => 'datetime',
        'is_disabled' => 'boolean',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

    ];

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
