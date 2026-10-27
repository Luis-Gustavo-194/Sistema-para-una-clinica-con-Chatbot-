<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;

// Portada pública: muestra especialidades y staff médico reales de la base de datos
class InicioController extends Controller
{
    public function index()
    {
        $especialidades = Especialidad::where('estado', 1)
            ->withCount(['medicos as medicos_activos_count' => fn ($q) => $q->activos()])
            ->orderBy('nombre')
            ->get();

        $medicos = Medico::with('especialidad')
            ->activos()
            ->whereHas('especialidad', fn ($q) => $q->where('estado', 1))
            ->orderBy('apellidos')
            ->get();

        $cifras = [
            'especialidades' => $especialidades->count(),
            'medicos' => $medicos->count(),
            'pacientes' => Paciente::count(),
            'atenciones' => Cita::where('estado', 'Atendida')->count(),
        ];

        return view('welcome', compact('especialidades', 'medicos', 'cifras'));
    }
}
