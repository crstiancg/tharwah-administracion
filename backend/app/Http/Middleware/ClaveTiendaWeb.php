<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas públicas de la tienda web (catálogo, contenido): sin sesión, las pide
 * el servidor de la web (nunca el navegador) con la clave de
 * `services.tienda_web.key` en `X-Api-Key`.
 */
class ClaveTiendaWeb
{
    public const ALIAS = 'clave.tienda-web';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clave = (string) config('services.tienda_web.key');

        // Sin clave configurada la tienda web está apagada: la ruta no existe.
        abort_if($clave === '', 404);
        abort_unless(hash_equals($clave, (string) $request->header('X-Api-Key')), 401, 'Clave de la tienda web incorrecta.');

        return $next($request);
    }
}
