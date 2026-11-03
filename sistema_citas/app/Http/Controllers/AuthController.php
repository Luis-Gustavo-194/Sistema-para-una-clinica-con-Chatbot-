<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validamos que nos envíen los datos
        $request->validate([
            'correo' => 'required|email',
            'contrasena' => 'required',
        ]);

        $usuario = Usuario::where('correo', $request->correo)->first();

        // 2. HU01: si la cuenta está bloqueada no se revisa la contraseña
        if ($usuario?->estaBloqueado()) {
            $this->auditarAcceso(Auditoria::ACCESO_CUENTA_BLOQUEADA, $usuario);

            return $this->rechazar($this->mensajeBloqueo($usuario));
        }

        // 3. Validamos la contraseña manualmente para poder contar los intentos fallidos
        if (! $usuario || ! Hash::check($request->contrasena, $usuario->contrasena)) {
            // HU17: si el correo no existe, el texto lleva el correo (se guarda enmascarado)
            $this->auditarAcceso(Auditoria::ACCESO_FALLIDO.($usuario ? '' : " ({$request->correo})"), $usuario);

            if ($usuario && $usuario->registrarIntentoFallido()) {
                $this->auditarAcceso(Auditoria::ACCESO_BLOQUEO, $usuario);

                return $this->rechazar($this->mensajeBloqueo($usuario));
            }

            // Mismo mensaje exista o no el correo, para no revelar qué cuentas existen
            return $this->rechazar('Las credenciales proporcionadas no son correctas.');
        }

        // Solo usuarios activos pueden entrar
        if ((int) $usuario->estado !== 1) {
            $this->auditarAcceso(Auditoria::ACCESO_USUARIO_INACTIVO, $usuario);

            return $this->rechazar('Tu usuario está desactivado. Comunícate con el administrador.');
        }

        $usuario->reiniciarIntentos();
        Auth::login($usuario);
        // Generamos una sesión segura
        $request->session()->regenerate();
        $this->auditarAcceso(Auditoria::ACCESO_INICIO, $usuario);

        // 4. Verificamos el rol (RBAC) para redirigir al panel correspondiente
        //    Administrador → /admin/dashboard · Médico → /dashboard-medico · Paciente → /paciente · Recepcionista → /recepcion/pacientes
        return redirect($usuario->rutaPanel());
    }

    public function logout(Request $request)
    {
        if ($usuario = Auth::user()) {
            $this->auditarAcceso(Auditoria::ACCESO_CIERRE, $usuario);
        }

        Auth::logout(); // Cierra la sesión de Laravel
        $request->session()->invalidate(); // Invalida la memoria de la sesión
        $request->session()->regenerateToken(); // Regenera el token CSRF por seguridad

        return redirect('/'); // Devuelve a la página principal
    }

    // HU17: inicios, cierres e intentos fallidos quedan en la bitácora (tabla usuarios)
    private function auditarAcceso(string $accion, ?Usuario $usuario): void
    {
        Auditoria::registrar($accion, 'usuarios', $usuario?->id, usuarioId: $usuario?->id);
    }

    private function rechazar(string $mensaje)
    {
        return back()->withInput(request()->only('correo'))->withErrors(['correo' => $mensaje]);
    }

    private function mensajeBloqueo(Usuario $usuario): string
    {
        $minutos = max(1, (int) ceil(now()->diffInSeconds($usuario->bloqueado_hasta) / 60));

        return "Tu cuenta está bloqueada temporalmente por varios intentos fallidos. Intenta de nuevo en {$minutos} minuto(s) o restablece tu contraseña.";
    }
}
