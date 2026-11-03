<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// HU03: el Administrador gestiona los usuarios y les asigna rol.
// El rol Médico se asigna desde el módulo de médicos, porque necesita su perfil (colegiatura, especialidad).
class UsuarioController extends Controller
{
    // Roles que se pueden asignar desde esta pantalla
    const ROLES_ASIGNABLES = [Rol::PACIENTE, Rol::RECEPCIONISTA, Rol::ADMINISTRADOR];

    // Roles de las cuentas de personal que se crean aquí
    const ROLES_PERSONAL = [Rol::RECEPCIONISTA, Rol::ADMINISTRADOR];

    public function index(Request $request)
    {
        $buscar = trim((string) $request->query('buscar'));
        $rol = $request->query('rol');

        $usuarios = Usuario::with('paciente')
            ->when($buscar !== '', fn ($q) => $q->where('correo', 'like', '%'.addcslashes($buscar, '%_\\').'%'))
            ->when(array_key_exists((int) $rol, Rol::NOMBRES), fn ($q) => $q->where('rol_id', (int) $rol))
            ->orderBy('rol_id')
            ->orderBy('correo')
            ->paginate(15)
            ->withQueryString();

        return view('admin.usuarios.index', [
            'usuarios' => $usuarios,
            'buscar' => $buscar,
            'rol' => $rol,
            'rolesAsignables' => self::ROLES_ASIGNABLES,
            'rolesPersonal' => self::ROLES_PERSONAL,
        ]);
    }

    // Crea una cuenta de personal; la persona fija su contraseña con el enlace que le llega por correo
    public function store(Request $request)
    {
        $datos = $request->validate([
            'correo' => ['required', 'email', 'max:150', 'unique:usuarios,correo'],
            'rol_id' => ['required', 'integer', Rule::in(self::ROLES_PERSONAL)],
        ]);

        $usuario = Usuario::create([
            'rol_id' => $datos['rol_id'],
            'correo' => $datos['correo'],
            'contrasena' => Hash::make(Str::random(40)),
            'estado' => 1,
        ]);

        $rolNombre = Rol::NOMBRES[$usuario->rol_id];
        Auditoria::registrar("Creó el usuario #{$usuario->id} con rol {$rolNombre}", 'usuarios', $usuario->id);
        Password::sendResetLink(['correo' => $usuario->correo]);

        return back()->with('exito', "Usuario {$usuario->correo} creado como {$rolNombre}. Le enviamos un correo para que cree su contraseña.");
    }

    public function asignarRol(Request $request, Usuario $usuario)
    {
        $datos = $request->validate([
            'rol_id' => ['required', 'integer', Rule::in(self::ROLES_ASIGNABLES)],
        ]);
        $nuevoRol = (int) $datos['rol_id'];

        if ($usuario->is(Auth::user())) {
            return back()->withErrors(['usuario' => 'No puedes cambiar tu propio rol.']);
        }
        if ((int) $usuario->rol_id === Rol::MEDICO) {
            return back()->withErrors(['usuario' => 'El rol de los médicos se gestiona desde el módulo de médicos.']);
        }
        if ($nuevoRol === Rol::PACIENTE && ! $usuario->paciente()->exists()) {
            return back()->withErrors(['usuario' => "{$usuario->correo} no tiene ficha de paciente; no se le puede asignar ese rol."]);
        }
        if ((int) $usuario->rol_id === $nuevoRol) {
            return back();
        }

        $rolAnterior = Rol::NOMBRES[$usuario->rol_id] ?? 'Sin rol';
        $usuario->update(['rol_id' => $nuevoRol]);

        Auditoria::registrar("Cambió el rol del usuario #{$usuario->id}: {$rolAnterior} → ".Rol::NOMBRES[$nuevoRol], 'usuarios', $usuario->id);

        return back()->with('exito', "{$usuario->correo} ahora tiene el rol ".Rol::NOMBRES[$nuevoRol].'.');
    }
}
