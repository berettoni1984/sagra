<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Queue Selection --}}
        <div
            class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content p-6">
                <div class="flex items-center gap-4">
                    <label class="text-sm font-medium text-gray-950 dark:text-white">
                        {{ __('filament.queue_label') }}
                    </label>
                    <select
                        wire:model.live="queueId"
                        class="fi-select-input rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900">
                        @foreach($this->getQueues() as $queue)
                            <option value="{{ $queue['id'] }}">{{ $queue['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Products Grid --}}
        @if($queueId)
            <div
                class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                    <h3 class="fi-section-heading text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('filament.Products') }}
                    </h3>
                </div>
                <div class="fi-section-content p-6">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                        @foreach($this->getProducts() as $product)
                            <button
                                type="button"
                                wire:click="addProduct({{ $product['id'] }})"
                                @class([
                                    'relative flex flex-col items-center justify-center p-4 rounded-lg border-2 transition-all',
                                    'border-gray-200 hover:border-primary-500 hover:bg-primary-50 dark:border-gray-700 dark:hover:border-primary-400 dark:hover:bg-primary-950' => !$product['is_out_of_stock'],
                                    'border-red-500 bg-red-50 hover:border-red-600 hover:bg-red-100 dark:border-red-600 dark:bg-red-950 dark:hover:border-red-500 dark:hover:bg-red-900' => $product['is_out_of_stock'],
                                ])
                            >
                                {{-- Numerazione prodotto --}}
                                <span
                                    class="absolute top-1 left-1 flex items-center justify-center min-w-[1.5rem] h-6 px-1.5 text-xs font-bold text-gray-700 bg-gray-200 rounded dark:text-gray-300 dark:bg-gray-700">
                                    {{ $product['number'] }}
                                </span>

                                {{-- Quantità nel carrello --}}
                                @if($product['total_in_cart'] > 0)
                                    <span
                                        class="absolute top-1 right-1 flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold text-white bg-success-600 rounded-full dark:bg-success-500">
                                        {{ $product['total_in_cart'] }}
                                    </span>
                                @endif

                                {{-- Warning fuori stock --}}
                                @if($product['is_out_of_stock'])
                                    <span
                                        class="absolute top-8 right-1 flex items-center justify-center px-2 py-0.5 text-xs font-bold text-white bg-red-600 rounded-full dark:bg-red-500">
                                        ⚠️
                                    </span>
                                @endif

                                <span
                                    class="text-base font-semibold text-gray-900 dark:text-white text-center line-clamp-2 mt-2">
                                    {{ $product['name'] }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                    € {{ number_format((float) $product['price'], 2, ',', '') }}
                                </span>
                                @if($product['is_out_of_stock'])
                                    <span class="text-xs text-red-600 dark:text-red-400 mt-1 font-bold uppercase">
                                        @if($product['has_insufficient_ingredients'])
                                            {{ __('filament.Ingredient Out of Stock') }}
                                        @else
                                            {{ __('filament.Out of Stock') }}
                                        @endif
                                    </span>
                                @else
                                    <span class="text-xs mt-1 @if($product['remaining_stock'] < 0) text-red-600 dark:text-red-400 font-bold @else text-gray-400 dark:text-gray-500 @endif">
                                        Stock: {{ $product['remaining_stock'] }}
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Order Items --}}
        @if(count($items) > 0)
            <div
                class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="fi-section-header flex items-center gap-x-3 px-6 py-4">
                    <h3 class="fi-section-heading text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('filament.Order Items') }} ({{ count($items) }})
                    </h3>
                </div>
                <div class="fi-section-content p-6">
                    <div class="space-y-2">
                        @foreach($this->getSortedEnrichedItems() as $enrichedItem)
                            @php
                                $item = $enrichedItem['item'];
                                $itemId = $enrichedItem['item_id'];
                                $originalIndex = $enrichedItem['original_index'];
                                $product = $enrichedItem['product'];
                                $rowTotal = $enrichedItem['row_total'];
                                $productNumber = $enrichedItem['product_number'];
                                $isOutOfStock = $enrichedItem['is_out_of_stock'];
                            @endphp
                            <div
                                wire:key="item-{{ $itemId }}"
                                @class([
                                    'flex flex-col gap-2 p-3 rounded-lg',
                                    'bg-gray-50 dark:bg-gray-800' => !$isOutOfStock,
                                    'bg-red-50 border-2 border-red-300 dark:bg-red-950 dark:border-red-700' => $isOutOfStock,
                                ])
                            >
                                <div class="flex items-center gap-3">
                                    {{-- Numero prodotto --}}
                                    <div class="flex items-center justify-center min-w-[2rem] h-8 px-2 text-sm font-bold text-gray-700 bg-gray-200 rounded dark:text-gray-300 dark:bg-gray-700 shrink-0">
                                        {{ $productNumber }}
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <div class="font-medium text-gray-900 dark:text-white truncate">
                                                {{ $product->name ?? '' }}
                                            </div>
                                            @if($isOutOfStock)
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 text-xs font-bold text-red-700 bg-red-200 rounded-full dark:text-red-200 dark:bg-red-800 shrink-0">
                                                    ⚠️ {{ __('filament.Out of Stock') }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            € {{ number_format((float) ($product->price ?? 0), 2, ',', '') }}
                                            × {{ $item['quantity'] }}
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        @if($item['quantity'] > 1)
                                            <button
                                                type="button"
                                                wire:click="splitItem({{ $originalIndex }})"
                                                title="{{ __('filament.Split item') }}"
                                                class="flex items-center justify-center rounded-lg w-8 h-8 bg-primary-100 hover:bg-primary-200 text-primary-700 dark:bg-primary-900 dark:hover:bg-primary-800 dark:text-primary-300 transition-colors"
                                            >
                                                <x-filament::icon
                                                    icon="heroicon-m-arrows-right-left"
                                                    class="h-4 w-4"
                                                />
                                            </button>
                                        @endif

                                        <button
                                            type="button"
                                            wire:click="decreaseQuantity({{ $originalIndex }})"
                                            class="flex items-center justify-center rounded-lg w-8 h-8 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors"
                                        >
                                            <x-filament::icon
                                                icon="heroicon-m-minus"
                                                class="h-4 w-4"
                                            />
                                        </button>

                                        <span
                                            class="min-w-[2.5rem] text-center font-semibold text-gray-900 dark:text-white">
                                            {{ $item['quantity'] }}
                                        </span>

                                        <button
                                            type="button"
                                            wire:click="increaseQuantity({{ $originalIndex }})"
                                            class="flex items-center justify-center rounded-lg w-8 h-8 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 transition-colors"
                                        >
                                            <x-filament::icon
                                                icon="heroicon-m-plus"
                                                class="h-4 w-4"
                                            />
                                        </button>
                                    </div>

                                    <div
                                        class="font-semibold text-gray-900 dark:text-white min-w-[4rem] text-right shrink-0">
                                        € {{ number_format($rowTotal, 2, ',', '') }}
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="removeProduct({{ $originalIndex }})"
                                        class="flex items-center justify-center rounded-lg w-8 h-8 text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-950 transition-colors shrink-0"
                                    >
                                        <x-filament::icon
                                            icon="heroicon-m-trash"
                                            class="h-5 w-5"
                                        />
                                    </button>
                                </div>

                                {{-- Item Note --}}
                                <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <input
                                        type="text"
                                        wire:model.live="items.{{ $originalIndex }}.note"
                                        placeholder="{{ __('filament.Order Item Note') }}..."
                                        class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Order Total --}}
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        @if($this->hasOutOfStockItems())
                            <div
                                class="mb-4 p-3 bg-yellow-50 border border-yellow-300 rounded-lg dark:bg-yellow-950 dark:border-yellow-700">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="text-yellow-700 dark:text-yellow-300">⚠️</span>
                                    <span class="font-medium text-yellow-800 dark:text-yellow-200">
                                        {{ __('filament.This order contains out of stock items') }}
                                    </span>
                                </div>
                            </div>
                        @endif

                        {{-- Order Note --}}
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ __('filament.Order Note') }}
                            </label>
                            <textarea
                                wire:model="note"
                                rows="2"
                                placeholder="{{ __('filament.Order Note') }}..."
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            ></textarea>
                        </div>

                        @if($this->isFreeConfigEnabled())
                            <div class="flex items-center gap-3 mb-4">
                                <input
                                    type="checkbox"
                                    wire:model="free"
                                    id="free-checkbox"
                                    class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                >
                                <label for="free-checkbox" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('filament.Free') }}
                                </label>
                            </div>
                        @endif

                        {{-- Change Price (Total Paid) --}}
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ __('filament.Change Price') }} ({{ __('filament.Total Paid') }})
                            </label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="number"
                                    wire:model.live="customTotalPaid"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                />
                                <span class="text-sm text-gray-500 dark:text-gray-400">€</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ __('filament.Leave empty to use full amount') }}
                            </p>
                        </div>

                        <div class="flex justify-between items-center text-xl font-bold">
                            <span class="text-gray-900 dark:text-white">{{ __('filament.Total') }}</span>
                            <span class="text-gray-900 dark:text-white">
                                € {{ number_format($this->getOrderTotal(), 2, ',', '') }}
                            </span>
                        </div>

                        {{-- Create Order Button (Bottom) --}}
                        <div class="mt-6">
                            <button
                                type="button"
                                wire:click="$dispatch('create-order')"
                                @if(empty($items))
                                    wire:confirm="{{ __('filament.Are you sure you want to create an empty order?') }}"
                                @endif
                                class="w-full py-3 px-4 rounded-lg bg-success-600 hover:bg-success-700 text-white font-semibold text-lg transition-colors shadow-lg hover:shadow-xl flex items-center justify-center gap-2"
                            >
                                <x-filament::icon
                                    icon="heroicon-o-check-circle"
                                    class="h-6 w-6"
                                />
                                <span>{{ __('filament.Create Order') }}</span>
                            </button>
                        </div>
                    </div>

                    {{-- Additional Options Removed (moved above) --}}
                </div>
            </div>
        @endif

        {{-- Fixed Create Order Button --}}
        @if(count($items) > 0)
            <div class="fixed bottom-6 right-6 z-50">
                <button
                    type="button"
                    wire:click="$dispatch('create-order')"
                    class="relative flex items-center justify-center w-16 h-16 rounded-full bg-success-600 hover:bg-success-700 text-white font-bold transition-all shadow-2xl hover:shadow-3xl hover:scale-110 ring-4 ring-success-200 dark:ring-success-900"
                    title="{{ __('filament.Create Order') }}"
                >
                    <x-filament::icon
                        icon="heroicon-o-shopping-cart"
                        class="h-8 w-8"
                    />
                    {{-- Badge con quantità totale prodotti --}}
                    <span class="absolute -top-1 -right-1 flex items-center justify-center min-w-[1.5rem] h-6 px-1.5 text-xs font-bold text-white bg-primary-600 rounded-full border-2 border-white dark:border-gray-900">
                        {{ $this->getTotalItemsCount() }}
                    </span>
                </button>
            </div>
        @endif
    </div>
</x-filament-panels::page>

