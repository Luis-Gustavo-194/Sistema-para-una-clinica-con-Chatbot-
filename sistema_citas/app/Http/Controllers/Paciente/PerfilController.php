<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerfilActual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Auditoria;

class PerfilController extends Controller
{
    use PerfilActual;

    public function edit()
    {
        return view('paciente.perfil', ['paciente' => $this->pacienteActual(), 'usuario' => Auth::user()]);
    }

    // Solo el teléfono es editable; DNI, nombres y fecha de nacimiento los corrige la clínica
    public function update(Request $request)
    {
        $paciente = $this->pacienteActual();
        $datos = $request->validate([
            'telefono' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s-]{6,20}$/'],
        ]);

        $paciente->update(['telefono' => $datos['telefono'] ?? null]);
        Auditoria::registrar('Actualizó su teléfono', 'pacientes', $paciente->id);

        return back()->with('exito', 'Datos de contacto actualizados.');
    }

    public function contrasena(Request $request)
    {
        $datos = $request->validate([
            'contrasena_actual' => ['required', 'current_password'],
            'contrasena' => ['required', 'string', 'min:8', 'confirmed', 'different:contrasena_actual'],
        ]);

        $usuario = Auth::user();
        $usuario->update(['contrasena' => Hash::make($datos['contrasena'])]);
        Auditoria::registrar('Cambió su contraseña', 'usuarios', $usuario->id);

        return back()->with('exito', 'Contraseña actualizada.');
    }
}
