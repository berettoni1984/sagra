<?php

namespace App\Filament\Resources\LogoResource\Pages;

use App\Filament\Resources\LogoResource;
use App\Models\Logo;
use Filament\Resources\Pages\CreateRecord;

class CreateLogo extends CreateRecord
{
    protected static string $resource = LogoResource::class;

    protected function afterCreate(): void
    {
        /** @var Logo $logo */
        $logo = $this->record;
        if ($logo->is_default) {
            Logo::where('is_default', true)
                ->where('id', '!=', $logo->id)
                ->update(['is_default' => false]);
        }
    }
}
