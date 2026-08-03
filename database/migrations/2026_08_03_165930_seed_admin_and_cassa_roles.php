<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea i ruoli admin/cassa e li assegna agli utenti già presenti.
 *
 * Prima di questa migration non esisteva alcun controllo di autorizzazione:
 * ogni utente autenticato poteva modificare gli altri utenti, cancellare le
 * configurazioni e azzerare la numerazione di tutte le code.
 *
 * Tutti gli utenti esistenti diventano admin: qualsiasi altra scelta
 * chiuderebbe fuori dal pannello chi lo sta già usando.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([User::ROLE_ADMIN, User::ROLE_CASSA] as $name) {
            Role::findOrCreate($name, 'web');
        }

        User::query()->each(static function (User $user): void {
            if ($user->roles()->exists()) {
                return;
            }
            $user->assignRole(User::ROLE_ADMIN);
        });
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()
            ->whereIn('name', [User::ROLE_ADMIN, User::ROLE_CASSA])
            ->get()
            ->each(static fn (Role $role) => $role->delete());
    }
};
