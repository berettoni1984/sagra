<?php

namespace App\Filament\Concerns;

/**
 * Toglie la risorsa ai camerieri, che in sala vedono solo l'elenco ordini e il
 * venduto per coda.
 *
 * Il blocco effettivo e' su rotta, in RestrictCameriereAccess; qui serve a non
 * lasciare nel menu una voce che porterebbe a un 403.
 */
trait HiddenFromCamerieri
{
    public static function canAccess(): bool
    {
        return ! (auth()->user()?->isCameriere() ?? false) && parent::canAccess();
    }
}
