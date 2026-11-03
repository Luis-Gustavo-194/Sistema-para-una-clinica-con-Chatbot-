<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

// HU04: recuperación de contraseña con el Password broker de Laravel
// (tabla password_reset_tokens; caducidad y límite de reenvío en config/auth.php)
class PasswordController extends Controller
{
    public function solicitar()
    {
        return view('auth.olvide-contrasena');
    }

    // Envía el enlace por correo. La respuesta es la misma exista o no la cuenta,
    // para no revelar qué correos están registrados.
    public function enviarEnlace(Request $request)
    {
        $request->validate(['correo' => ['required', 'email']]);

        $estado = Password::sendResetLink(['correo' => $request->correo]);

        if ($estado === Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors(['correo' => 'Ya te enviamos un enlace hace poco. Espera un minuto antes de pedir otro.']);
        }

        return back()->with('exito', 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña. Revisa tu bandeja de entrada.');
    }

    public function formulario(Request $request, string $token)
    {
        return view('auth.nueva-contrasena', [
            'token' => $token,
            'correo' => $request->query('correo'),
        ]);
    }

    public function restablecer(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'correo' => ['required', 'email'],
            'contrasena' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // El broker comprueba que el token exista, no haya vencido y sea del correo; luego lo borra
        $estado = Password::reset([
            'correo' => $request->correo,
            'token' => $request->token,
            'password' => $request->contrasena,
        ], function (Usuario $usuario, string $contrasena) {
            $usuario->forceFill([
                'contrasena' => Hash::make($contrasena),
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();

            Auditoria::registrar('Restableció su contraseña por correo', 'usuarios', $usuario->id, usuarioId: $usuario->id);
        });

        if ($estado !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('correo'))->withErrors([
                'correo' => 'El enlace para restablecer la contraseña no es válido o ya venció. Solicita uno nuevo.',
            ]);
        }

        return redirect()->route('login')->with('exito', 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.');
    }
}
