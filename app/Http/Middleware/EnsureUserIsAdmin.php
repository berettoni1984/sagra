<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consente il passaggio solo agli utenti con ruolo admin.
 *
 * Serve per le rotte fuori dal pannello Filament, dove i gate canAccess()
 * delle risorse non entrano in gioco.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin() ?? false, 403);

        return $next($request);
    }
}
