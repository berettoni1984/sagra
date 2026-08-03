<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $amount = fake()->randomFloat(2, 1, 20);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            // il nome è storicizzato sulla riga, non letto dal prodotto
            'name' => fake()->words(2, true),
            'quantity' => $quantity,
            'amount' => $amount,
            'row_amount' => round($quantity * $amount, 2),
            'note' => null,
        ];
    }

    /** Riga il cui prodotto è stato cancellato: resta lo storico. */
    public function withoutProduct(): static
    {
        return $this->state(['product_id' => null]);
    }

    public function of(Product $product, int $quantity = 1): static
    {
        return $this->state([
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => $quantity,
            'amount' => $product->price,
            'row_amount' => round($quantity * (float) $product->price, 2),
        ]);
    }
}
