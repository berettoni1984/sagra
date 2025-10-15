<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>


    <iframe class="w-full min-h-screen"  src='{{route('order.invoice',['orderId'=>$this->getRecord(),'print'=>request('print',false)])}}'></iframe>
</x-filament-panels::page>
