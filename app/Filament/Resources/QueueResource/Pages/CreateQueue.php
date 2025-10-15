<?php

namespace App\Filament\Resources\QueueResource\Pages;

use App\Filament\Resources\QueueResource;
use App\Models\Queue;
use Filament\Resources\Pages\CreateRecord;

class CreateQueue extends CreateRecord
{
    protected static string $resource = QueueResource::class;

    protected function afterCreate(): void
    {
        /** @var Queue $queue */
        $queue = $this->record;
        if ($queue->is_default) {
            Queue::where('is_default', true)
                ->where('id', '!=', $queue->id)
                ->update(['is_default' => false]);
        }
    }
}
