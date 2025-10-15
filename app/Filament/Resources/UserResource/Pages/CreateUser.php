<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function beforeValidate(): void
    {
        $passwordS1 = $this->data['passwordS1'] ?? null;
        $passwordS2 = $this->data['passwordS2'] ?? null;
        if (
            $passwordS1 && $passwordS2 &&
            ($passwordS1 === $passwordS2)
        ) {
            return;
        }
        Notification::make()
            ->title('Password Error')
            ->danger()
            ->send();
        throw new Halt;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $passwordS1 = $data['passwordS1'] ?? null;
        $passwordS2 = $data['passwordS2'] ?? null;
        if ($passwordS1 && $passwordS2 && $passwordS1 === $passwordS2) {
            $data['password'] = \Hash::make($passwordS1);
        }
        if (! ($passwordS1 && $passwordS2 && $passwordS1 === $passwordS2)) {
            unset($data['password']);
        }

        return parent::mutateFormDataBeforeCreate($data);
    }
}
