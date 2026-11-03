<?php

namespace App\Http\Controllers;

use App\Models\Especialidad;
use App\Models\Medico;
use App\Services\HorarioService;
use Illuminate\Http\Request;

// Directorio público: médicos activos (de especialidades activas) con sus días y horas de atención
class DirectorioController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim((string) $request->query('buscar'));
        $especialidades = Especialidad::where('estado', 1)->orderBy('nombre')->get();
        $especialidadId = $especialidades->firstWhere('id', (int) $request->query('especialidad'))?->id;

        $medicos = Medico::with(['especialidad', 'horarios'])
            ->activos()
            ->whereHas('especialidad', fn ($q) => $q->where('estado', 1))
            ->when($especialidadId, fn ($q) => $q->where('especialidad_id', $especialidadId))
            ->when($buscar !== '', function ($q) use ($buscar) {
                $texto = '%'.addcslashes($buscar, '%_\\').'%';
                $q->where(fn ($q) => $q->where('nombres', 'like', $texto)->orWhere('apellidos', 'like', $texto));
            })
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get()
            ->each(fn (Medico $medico) => $medico->setAttribute('atencion', HorarioService::resumen($medico->horarios)));

        return view('directorio', compact('medicos', 'especialidades', 'especialidadId', 'buscar'));
    }
}
