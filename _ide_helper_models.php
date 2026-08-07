<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * App\Models\Config
 *
 * @property int $id
 * @property string $code
 * @property string $config_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereConfigValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereUpdatedAt($value)
 * @property string|null $comment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereComment($value)
 * @mixin \Eloquent
 * @method static \Database\Factories\ConfigFactory factory($count = null, $state = [])
 */
	class Config extends \Eloquent {}
}

namespace App\Models{
/**
 * @property ?Pivot $pivot
 * @property int $id
 * @property string $name
 * @property int $stock
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 * @method static \Database\Factories\IngredientFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ingredient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ingredient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ingredient query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ingredient whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ingredient whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ingredient whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ingredient whereStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ingredient whereUpdatedAt($value)
 * @property int $is_disabled
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ingredient whereIsDisabled($value)
 * @mixin \Eloquent
 */
	class Ingredient extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $path
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static \Database\Factories\LogoFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	class Logo extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\Order
 *
 * @property int $id
 * @property int $number
 * @property string $total_amount
 * @property string $total_paid
 * @property string|null $note
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, OrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order withoutTrashed()
 * @property-read string $number_queue
 * @property int|null $queue_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereQueueId($value)
 * @property-read Queue|null $queue
 * @property int|null $user_id
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUserId($value)
 * @mixin \Eloquent
 * @method static \Database\Factories\OrderFactory factory($count = null, $state = [])
 */
	class Order extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\OrderItem
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property string $name
 * @property int $quantity
 * @property string $amount
 * @property string $row_amount
 * @property string|null $note
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereRowAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem withoutTrashed()
 * @property-read Product|null $product
 * @mixin \Eloquent
 * @method static \Database\Factories\OrderItemFactory factory($count = null, $state = [])
 */
	class OrderItem extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\Product
 *
 * @property int $id
 * @property string $name
 * @property string $price
 * @property int $is_disabled
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $label
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
 * @property-read Collection<int, OrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @property int $stock
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereStock($value)
 * @property int $backorder
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereBackorder($value)
 * @property-read Collection<int, Queue> $queues
 * @property-read int|null $queues_count
 * @property-read Collection<int, Ingredient> $ingredients
 * @property-read int|null $ingredients_count
 * @mixin \Eloquent
 * @method static \Database\Factories\ProductFactory factory($count = null, $state = [])
 */
	class Product extends \Eloquent {}
}

namespace App\Models{
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
 * @property-read Collection<int, Order> $orders
 * @property-read int|null $orders_count
 * @property-read string $label
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 * @mixin \Eloquent
 */
	class Queue extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $use_factory
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @property string|null $code
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCode($value)
 * @property-read Collection<int, Order> $orders
 * @property-read int|null $orders_count
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $teams
 * @property-read int|null $teams_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User team($teams, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTeam($teams)
 */
	class User extends \Eloquent implements \Filament\Models\Contracts\FilamentUser {}
}

