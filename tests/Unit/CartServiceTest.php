<?php

use App\Services\CartService;

beforeEach(function () {
    $this->cart = new CartService;
});

function cartRow(int $productId, int $quantity = 1, ?string $note = null): array
{
    return ['item_id' => 'id-'.$productId.'-'.$quantity, 'product_id' => $productId, 'quantity' => $quantity, 'note' => $note];
}

it('aggiunge un prodotto nuovo come riga separata', function () {
    $items = $this->cart->addProduct([], 7);

    expect($items)->toHaveCount(1)
        ->and($items[0]['product_id'])->toBe(7)
        ->and($items[0]['quantity'])->toBe(1)
        ->and($items[0]['item_id'])->toBeString()->not->toBeEmpty();
});

it('incrementa la riga esistente invece di duplicarla', function () {
    $items = $this->cart->addProduct([cartRow(7)], 7);

    expect($items)->toHaveCount(1)->and($items[0]['quantity'])->toBe(2);
});

it('rimuove una riga e reindicizza le chiavi', function () {
    $items = $this->cart->removeProduct([cartRow(1), cartRow(2), cartRow(3)], 1);

    expect($items)->toHaveCount(2)
        ->and(array_keys($items))->toBe([0, 1])
        ->and(array_column($items, 'product_id'))->toBe([1, 3]);
});

it('decrementando a zero rimuove la riga', function () {
    $items = $this->cart->decreaseQuantity([cartRow(5, 1)], 0);

    expect($items)->toBeEmpty();
});

it('decrementa senza rimuovere quando la quantita e maggiore di uno', function () {
    $items = $this->cart->decreaseQuantity([cartRow(5, 3)], 0);

    expect($items[0]['quantity'])->toBe(2);
});

it('ignora indici inesistenti senza errori', function () {
    expect($this->cart->increaseQuantity([], 99))->toBeEmpty()
        ->and($this->cart->decreaseQuantity([], 99))->toBeEmpty();
});

it('divide una riga creando una nuova riga da un pezzo', function () {
    $items = $this->cart->splitItem([cartRow(5, 3)], 0);

    expect($items)->toHaveCount(2)
        ->and($items[0]['quantity'])->toBe(2)
        ->and($items[1]['quantity'])->toBe(1)
        ->and($items[1]['product_id'])->toBe(5)
        ->and($items[1]['item_id'])->not->toBe($items[0]['item_id']);
});

it('non divide una riga da un solo pezzo', function () {
    $items = $this->cart->splitItem([cartRow(5, 1)], 0);

    expect($items)->toHaveCount(1);
});

it('somma le quantita totali e per prodotto', function () {
    $items = [cartRow(1, 2), cartRow(2, 3), cartRow(1, 4)];

    expect($this->cart->getTotalItemsCount($items))->toBe(9)
        ->and($this->cart->getTotalInCart($items, 1))->toBe(6)
        ->and($this->cart->getTotalInCart($items, 99))->toBe(0);
});
