<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validamos que nos envíen los datos
        $request->validate([
            'correo' => 'required|email',
            'contrasena' => 'required'
        ]);

        // 2. Intentamos iniciar sesión con la estructura de Laravel
        $credenciales = [
            'correo' => $request->correo,
            'password' => $request->contrasena, // Usamos 'password' aquí porque es la palabra clave interna de Laravel
            'estado' => 1 // Solo usuarios activos pueden entrar
        ];

        if (Auth::attempt($credenciales)) {
            // Generamos una sesión segura
            $request->session()->regenerate();
            $usuario = Auth::user();

            // 3. Verificamos el rol (RBAC) para redirigir al panel correspondiente
            //    Administrador → /admin/dashboard · Médico → /dashboard-medico · Paciente → /paciente
            return redirect($usuario->rutaPanel());
        }

        // Si la contraseña es incorrecta, regresamos con un error
        return back()->withErrors([
            'correo' => 'Las credenciales proporcionadas no son correctas.',
        ]);
    }
    
    public function logout(Request $request)
    {
        Auth::logout(); // Cierra la sesión de Laravel
        $request->session()->invalidate(); // Invalida la memoria de la sesión
        $request->session()->regenerateToken(); // Regenera el token CSRF por seguridad
        return redirect('/'); // Devuelve a la página principal
    }
}