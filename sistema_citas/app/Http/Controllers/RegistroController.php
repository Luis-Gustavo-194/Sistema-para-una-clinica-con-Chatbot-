<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Auditoria;
use App\Models\Paciente;
use App\Models\Rol;
use App\Models\Usuario;

// Registro público de pacientes nuevos: crea su usuario (rol Paciente) y su ficha
class RegistroController extends Controller
{
    public function create()
    {
        if (Auth::check()) {
            return redirect(Auth::user()->rutaPanel());
        }

        return view('registro');
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'dni' => ['required', 'digits:8', 'unique:pacientes,dni'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s-]{6,20}$/'],
            'fecha_nacimiento' => ['required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'correo' => ['required', 'email', 'max:150', 'unique:usuarios,correo'],
            'contrasena' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        [$usuario, $paciente] = DB::transaction(function () use ($datos) {
            $usuario = Usuario::create([
                'rol_id' => Rol::PACIENTE,
                'correo' => $datos['correo'],
                'contrasena' => Hash::make($datos['contrasena']),
                'estado' => 1,
            ]);

            $paciente = Paciente::create([
                'usuario_id' => $usuario->id,
                'dni' => $datos['dni'],
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'telefono' => $datos['telefono'] ?? null,
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
            ]);

            return [$usuario, $paciente];
        });

        Auth::login($usuario);
        $request->session()->regenerate();
        Auditoria::registrar("Se registró como paciente (DNI {$datos['dni']})", 'pacientes', $paciente->id);

        return redirect()->route('paciente.panel')
            ->with('exito', "¡Bienvenido(a), {$datos['nombres']}! Tu cuenta fue creada. Ya puedes agendar tu primera cita.");
    }
}
