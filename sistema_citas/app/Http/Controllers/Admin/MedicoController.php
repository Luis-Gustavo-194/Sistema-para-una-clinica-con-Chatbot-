<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\Auditoria;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Rol;
use App\Models\Usuario;

class MedicoController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim((string) $request->query('buscar'));
        $especialidadId = $request->query('especialidad');
        $estado = $request->query('estado'); // '1' activos, '0' inactivos, vacío = todos

        $medicos = Medico::with(['especialidad', 'usuario'])
            ->withCount(['citas', 'horarios'])
            ->withCount(['citas as citas_futuras_count' => fn ($q) => $q->noCanceladas()->where('fecha', '>=', today()->toDateString())])
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nombres', 'like', "%{$buscar}%")
                ->orWhere('apellidos', 'like', "%{$buscar}%")
                ->orWhere('colegiatura', 'like', "%{$buscar}%")))
            ->when($especialidadId, fn ($q) => $q->where('especialidad_id', $especialidadId))
            ->when(in_array($estado, ['0', '1'], true), fn ($q) => $q->whereHas('usuario', fn ($u) => $u->where('estado', $estado)))
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate(10)
            ->withQueryString();

        $especialidades = Especialidad::orderBy('nombre')->get();

        return view('admin.medicos.index', compact('medicos', 'especialidades', 'buscar', 'especialidadId', 'estado'));
    }

    public function create()
    {
        return view('admin.medicos.form', [
            'medico' => new Medico(),
            'especialidades' => $this->especialidadesDisponibles(),
        ]);
    }

    // Crea el usuario (rol Médico) y el perfil del médico en una sola transacción
    public function store(Request $request)
    {
        $datos = $this->validar($request);

        $medico = DB::transaction(function () use ($datos) {
            $usuario = Usuario::create([
                'rol_id' => Rol::MEDICO,
                'correo' => $datos['correo'],
                'contrasena' => Hash::make($datos['contrasena']),
                'estado' => 1,
            ]);

            return Medico::create(Arr::only($datos, ['especialidad_id', 'colegiatura', 'nombres', 'apellidos']) + [
                'usuario_id' => $usuario->id,
            ]);
        });

        Auditoria::registrar("Creó al médico {$medico->nombres} {$medico->apellidos}", 'medicos', $medico->id);

        return redirect()->route('admin.medicos.index')
            ->with('exito', "Dr(a). {$medico->nombres} {$medico->apellidos} registrado(a). Ya puede iniciar sesión con {$datos['correo']}.");
    }

    public function edit(Medico $medico)
    {
        return view('admin.medicos.form', [
            'medico' => $medico,
            'especialidades' => $this->especialidadesDisponibles($medico),
        ]);
    }

    // Actualiza datos del médico y el correo de su usuario; la contraseña solo si se escribe una nueva
    public function update(Request $request, Medico $medico)
    {
        $datos = $this->validar($request, $medico);

        DB::transaction(function () use ($medico, $datos) {
            $medico->update(Arr::only($datos, ['especialidad_id', 'colegiatura', 'nombres', 'apellidos']));

            $cambiosUsuario = ['correo' => $datos['correo']];
            if (!empty($datos['contrasena'])) {
                $cambiosUsuario['contrasena'] = Hash::make($datos['contrasena']);
            }
            $medico->usuario->update($cambiosUsuario);
        });

        Auditoria::registrar("Editó al médico {$medico->nombres} {$medico->apellidos}", 'medicos', $medico->id);

        return redirect()->route('admin.medicos.index')
            ->with('exito', "Datos de Dr(a). {$medico->nombres} {$medico->apellidos} actualizados.");
    }

    // Activar / desactivar: un médico inactivo no puede iniciar sesión ni aparece en el chatbot.
    // Sus citas ya agendadas no se modifican.
    public function cambiarEstado(Medico $medico)
    {
        $usuario = $medico->usuario;
        $usuario->update(['estado' => $usuario->estado ? 0 : 1]);
        $accion = $usuario->estado ? 'Activó' : 'Desactivó';

        Auditoria::registrar("{$accion} al médico {$medico->nombres} {$medico->apellidos}", 'medicos', $medico->id);

        return back()->with('exito', "Dr(a). {$medico->nombres} {$medico->apellidos} " . ($usuario->estado ? 'activado(a).' : 'desactivado(a).'));
    }

    // Solo se puede eliminar si no tiene ninguna cita (la BD lo impide con RESTRICT y se pierde el historial)
    public function destroy(Medico $medico)
    {
        if ($medico->citas()->exists()) {
            return back()->withErrors([
                'medico' => "No se puede eliminar a Dr(a). {$medico->nombres} {$medico->apellidos} porque tiene citas registradas. Puedes desactivarlo(a).",
            ]);
        }

        $nombre = "{$medico->nombres} {$medico->apellidos}";
        $id = $medico->id;

        // Se borra también su usuario de acceso (los horarios se borran en cascada)
        DB::transaction(function () use ($medico) {
            $usuario = $medico->usuario;
            $medico->delete();
            $usuario?->delete();
        });

        Auditoria::registrar("Eliminó al médico {$nombre}", 'medicos', $id);

        return redirect()->route('admin.medicos.index')
            ->with('exito', "Dr(a). {$nombre} eliminado(a).");
    }

    // Especialidades activas; al editar, también la actual aunque esté inactiva
    private function especialidadesDisponibles(?Medico $medico = null)
    {
        return Especialidad::where('estado', 1)
            ->when($medico, fn ($q) => $q->orWhere('id', $medico->especialidad_id))
            ->orderBy('nombre')
            ->get();
    }

    private function validar(Request $request, ?Medico $medico = null): array
    {
        return $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'colegiatura' => ['required', 'string', 'max:50', Rule::unique('medicos', 'colegiatura')->ignore($medico?->id)],
            'especialidad_id' => ['required', 'integer', Rule::exists('especialidades', 'id')->where(
                fn ($q) => $q->where('estado', 1)->when($medico, fn ($q) => $q->orWhere('id', $medico->especialidad_id))
            )],
            'correo' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'correo')->ignore($medico?->usuario_id)],
            'contrasena' => [$medico ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);
    }
}
