<?php

namespace App\Http\Middleware;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ProductResource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chiude il pannello a chi ha il ruolo camerieri, tranne le due pagine di sala.
 *
 * Il controllo sta qui e non nei canAccess() delle risorse perche' il permesso
 * non e' per risorsa ma per pagina: dei prodotti il cameriere vede solo il
 * venduto per coda, degli ordini l'elenco e il dettaglio, e canAccess() vale per tutte
 * le pagine di una risorsa insieme. Una regola sola sulla rotta e' anche piu'
 * difficile da bucare di un elenco di override sparsi sulle singole pagine.
 */
class RestrictCameriereAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ($request->user()?->isCameriere() ?? false)) {
            return $next($request);
        }

        abort_unless(in_array($request->route()?->getName(), self::allowedRouteNames(), true), 403);

        return $next($request);
    }

    /**
     * Le rotte del pannello aperte ai camerieri.
     *
     * @return array<int, string>
     */
    public static function allowedRouteNames(): array
    {
        return [
            'filament.admin.auth.logout',
            'filament.admin.home',
            OrderResource::getRouteBaseName().'.index',
            OrderResource::getRouteBaseName().'.view',
            ProductResource::getRouteBaseName().'.sold',
        ];
    }
}
