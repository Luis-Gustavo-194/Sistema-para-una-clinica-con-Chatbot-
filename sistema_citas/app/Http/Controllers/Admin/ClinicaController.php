<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Configuracion;
use Illuminate\Http\Request;

// Mantenimiento: datos de contacto de la clínica que se muestran en el portal, el directorio y los correos
class ClinicaController extends Controller
{
    // Campo del formulario => clave de config('clinica.*')
    const CAMPOS = [
        'nombre' => 'nombre',
        'eslogan' => 'eslogan',
        'ciudad' => 'ciudad',
        'direccion' => 'direccion',
        'telefono_celular' => 'telefonos.celular',
        'telefono_fijo' => 'telefonos.fijo',
        'correo' => 'correo',
        'horario' => 'horario',
    ];

    public function edit()
    {
        $valores = [];
        foreach (self::CAMPOS as $campo => $clave) {
            $valores[$campo] = config("clinica.{$clave}");
        }

        return view('admin.clinica', compact('valores'));
    }

    public function update(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'eslogan' => ['nullable', 'string', 'max:150'],
            'ciudad' => ['required', 'string', 'max:60'],
            'direccion' => ['required', 'string', 'max:150'],
            'telefono_celular' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s()-]{6,20}$/'],
            'telefono_fijo' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s()-]{6,20}$/'],
            'correo' => ['required', 'email', 'max:150'],
            'horario' => ['required', 'string', 'max:150'],
        ], [], [
            'eslogan' => 'eslogan',
            'ciudad' => 'ciudad',
            'direccion' => 'dirección',
            'telefono_celular' => 'celular',
            'telefono_fijo' => 'teléfono fijo',
            'horario' => 'horario de atención',
        ]);

        $valores = [];
        foreach (self::CAMPOS as $campo => $clave) {
            $valores[$clave] = $datos[$campo] ?? null;
        }
        Configuracion::guardar($valores);

        Auditoria::registrar('Actualizó los datos de la clínica', 'configuraciones');

        return redirect()->route('admin.clinica.edit')->with('exito', 'Datos de la clínica guardados. Ya se muestran en el portal y en el directorio.');
    }
}
