<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * App\Models\Product
 *
 * @property int $id
 * @property string $name
 * @property string $price
 * @property int $is_disabled
 * @property int $order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $label
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereIsDisabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereOrder($value)
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @property int $stock
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereStock($value)
 *
 * @property int $backorder
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereBackorder($value)
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Queue> $queues
 * @property-read int|null $queues_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ingredient> $ingredients
 * @property-read int|null $ingredients_count
 *
 * @mixin \Eloquent
 */
class Product extends Model
{
    /**
     * {@inheritDoc}
     */
    protected $fillable = [
        'name',
        'price',
        'stock',
        'backorder',
        'is_disabled',
        'order',
    ];

    /**
     * @return Attribute<string,string>
     */
    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->name,// .' '.number_format((float) $this->price, 2, ',', '').' €',
        );
    }

    /**
     * @return BelongsToMany<Queue,$this,Pivot>
     */
    public function queues(): BelongsToMany
    {
        return $this->belongsToMany(Queue::class);
    }

    /**
     * @return BelongsToMany<Ingredient,$this,Pivot>
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredient', 'product_id', 'ingredient_id')
            ->withPivot('qty');
    }

    /**
     * @return HasMany<OrderItem,$this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
