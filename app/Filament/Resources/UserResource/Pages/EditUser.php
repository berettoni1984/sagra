<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function beforeValidate(): void
    {
        $passwordS1 = $this->data['passwordS1'] ?? null;
        $passwordS2 = $this->data['passwordS2'] ?? null;
        if (
            (! $passwordS1 && ! $passwordS2) ||
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $passwordS1 = $data['passwordS1'] ?? null;
        $passwordS2 = $data['passwordS2'] ?? null;
        if ($passwordS1 && $passwordS2 && $passwordS1 === $passwordS2) {
            $data['password'] = \Hash::make($passwordS1);
        }
        if (! ($passwordS1 && $passwordS2 && $passwordS1 === $passwordS2)) {
            unset($data['password']);
        }

        return parent::mutateFormDataBeforeSave($data);
    }
}
