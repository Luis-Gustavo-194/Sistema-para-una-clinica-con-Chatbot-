<?php

namespace App\Http\Controllers\Recepcion;

use App\Http\Controllers\Controller;
use App\Http\Requests\PacienteRequest;
use App\Models\Auditoria;
use App\Models\Paciente;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

// HU05 y HU06: recepción busca, consulta, registra y edita las fichas de los pacientes
class PacienteController extends Controller
{
    private const CAMPOS_FICHA = ['dni', 'nombres', 'apellidos', 'telefono', 'fecha_nacimiento', 'seguro'];

    public function index(Request $request)
    {
        $buscar = trim((string) $request->query('buscar'));

        $pacientes = Paciente::with('usuario')
            ->buscar($buscar)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate(15)
            ->withQueryString();

        return view('recepcion.pacientes.index', compact('pacientes', 'buscar'));
    }

    public function show(Paciente $paciente)
    {
        $paciente->load('usuario');
        $citas = $paciente->citas()
            ->with('medico.especialidad')
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->limit(10)
            ->get();

        return view('recepcion.pacientes.show', compact('paciente', 'citas'));
    }

    public function create()
    {
        return view('recepcion.pacientes.form', ['paciente' => new Paciente]);
    }

    // Crea el usuario (rol Paciente) con una contraseña aleatoria y la ficha;
    // luego el paciente recibe por correo el enlace para crear su propia contraseña.
    public function store(PacienteRequest $request)
    {
        $datos = $request->validated();

        $paciente = DB::transaction(function () use ($datos) {
            $usuario = Usuario::create([
                'rol_id' => Rol::PACIENTE,
                'correo' => $datos['correo'],
                'contrasena' => Hash::make(Str::random(40)),
                'estado' => 1,
            ]);

            return Paciente::create(Arr::only($datos, self::CAMPOS_FICHA) + [
                'usuario_id' => $usuario->id,
                'consentimiento_datos_at' => now(),
            ]);
        });

        Auditoria::registrar("Registró al paciente #{$paciente->id} desde recepción", 'pacientes', $paciente->id);
        Password::sendResetLink(['correo' => $datos['correo']]);

        return redirect()->route('recepcion.pacientes.show', $paciente)
            ->with('exito', "Paciente {$paciente->nombres} {$paciente->apellidos} registrado(a). Le enviamos un correo para que cree su contraseña.");
    }

    public function edit(Paciente $paciente)
    {
        $paciente->load('usuario');

        return view('recepcion.pacientes.form', compact('paciente'));
    }

    public function update(PacienteRequest $request, Paciente $paciente)
    {
        $datos = $request->validated();
        $paciente->fill(Arr::only($datos, self::CAMPOS_FICHA));
        $usuario = $paciente->usuario;
        $usuario?->fill(['correo' => $datos['correo']]);

        // Campos que realmente cambiaron, para la bitácora
        $cambios = array_keys($paciente->getDirty());
        if ($usuario?->isDirty('correo')) {
            $cambios[] = 'correo';
        }

        if ($cambios === []) {
            return redirect()->route('recepcion.pacientes.show', $paciente)->with('exito', 'No hubo cambios en la ficha.');
        }

        DB::transaction(function () use ($paciente, $usuario) {
            $paciente->save();
            $usuario?->save();
        });

        Auditoria::registrar("Editó la ficha del paciente #{$paciente->id} (".implode(', ', $cambios).')', 'pacientes', $paciente->id);

        return redirect()->route('recepcion.pacientes.show', $paciente)
            ->with('exito', "Ficha de {$paciente->nombres} {$paciente->apellidos} actualizada.");
    }
}
