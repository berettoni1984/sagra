<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea il ruolo camerieri, di sola lettura sulla sala.
 *
 * A differenza di admin e cassa non tocca nessun utente esistente: il ruolo
 * nasce vuoto e si assegna dal pannello utenti. Chi lo ha vede soltanto
 * l'elenco ordini e il venduto per coda (vedi RestrictCameriereAccess).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate(User::ROLE_CAMERIERI, 'web');
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()
            ->where('name', User::ROLE_CAMERIERI)
            ->where('guard_name', 'web')
            ->get()
            ->each(static fn (Role $role) => $role->delete());
    }
};
