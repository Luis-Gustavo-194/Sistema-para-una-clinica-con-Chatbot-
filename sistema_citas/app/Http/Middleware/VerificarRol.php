<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerificarRol
{
    // Uso en rutas: ->middleware('rol:' . Rol::ADMINISTRADOR) — acepta varios separados por coma
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        // Si el usuario fue desactivado mientras tenía la sesión abierta, se la cerramos
        if (Auth::check() && (int) Auth::user()->estado !== 1) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors([
                'correo' => 'Tu usuario está desactivado. Comunícate con el administrador.',
            ]);
        }

        if (!Auth::check() || !in_array((string) Auth::user()->rol_id, $roles, true)) {
            return redirect('/login')->withErrors([
                'correo' => 'Acceso denegado. No tienes permisos para entrar a esa sección.',
            ]);
        }

        return $next($request);
    }
}
