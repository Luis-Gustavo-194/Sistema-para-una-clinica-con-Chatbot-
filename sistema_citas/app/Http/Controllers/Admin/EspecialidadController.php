<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Auditoria;
use App\Models\Especialidad;

class EspecialidadController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim((string) $request->query('buscar'));

        $especialidades = Especialidad::withCount('medicos')
            ->when($buscar !== '', fn ($q) => $q->where('nombre', 'like', "%{$buscar}%"))
            ->orderBy('nombre')
            ->get();

        return view('admin.especialidades.index', compact('especialidades', 'buscar'));
    }

    public function create()
    {
        return view('admin.especialidades.form', ['especialidad' => new Especialidad()]);
    }

    public function store(Request $request)
    {
        $especialidad = Especialidad::create($this->validar($request) + ['estado' => 1]);

        Auditoria::registrar("Creó la especialidad {$especialidad->nombre}", 'especialidades', $especialidad->id);

        return redirect()->route('admin.especialidades.index')
            ->with('exito', "Especialidad «{$especialidad->nombre}» creada correctamente.");
    }

    public function edit(Especialidad $especialidad)
    {
        return view('admin.especialidades.form', compact('especialidad'));
    }

    public function update(Request $request, Especialidad $especialidad)
    {
        $especialidad->update($this->validar($request, $especialidad));

        Auditoria::registrar("Editó la especialidad {$especialidad->nombre}", 'especialidades', $especialidad->id);

        return redirect()->route('admin.especialidades.index')
            ->with('exito', "Especialidad «{$especialidad->nombre}» actualizada.");
    }

    // Activar / desactivar: una especialidad inactiva deja de ofrecerse en el chatbot
    public function cambiarEstado(Especialidad $especialidad)
    {
        $especialidad->update(['estado' => $especialidad->estado ? 0 : 1]);
        $accion = $especialidad->estado ? 'Activó' : 'Desactivó';

        Auditoria::registrar("{$accion} la especialidad {$especialidad->nombre}", 'especialidades', $especialidad->id);

        return back()->with('exito', "Especialidad «{$especialidad->nombre}» " . ($especialidad->estado ? 'activada.' : 'desactivada.'));
    }

    // Solo se puede eliminar si no tiene médicos (la BD lo impide con RESTRICT)
    public function destroy(Especialidad $especialidad)
    {
        if ($especialidad->medicos()->exists()) {
            return back()->withErrors([
                'especialidad' => "No se puede eliminar «{$especialidad->nombre}» porque tiene médicos asignados. Puedes desactivarla.",
            ]);
        }

        $nombre = $especialidad->nombre;
        $id = $especialidad->id;
        $especialidad->delete();

        Auditoria::registrar("Eliminó la especialidad {$nombre}", 'especialidades', $id);

        return redirect()->route('admin.especialidades.index')
            ->with('exito', "Especialidad «{$nombre}» eliminada.");
    }

    private function validar(Request $request, ?Especialidad $especialidad = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('especialidades', 'nombre')->ignore($especialidad?->id)],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
