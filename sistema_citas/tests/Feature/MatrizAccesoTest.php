<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// HU03: matriz rol × ruta web. Cada rol entra solo a sus pantallas;
// en las demás (o sin sesión) se le devuelve al login.
class MatrizAccesoTest extends TestCase
{
    use EsquemaClinica;

    // Ruta => roles que pueden entrar
    private const MATRIZ = [
        '/paciente' => ['paciente'],
        '/paciente/citas' => ['paciente'],
        '/dashboard-medico' => ['medico'],
        '/medico/horarios' => ['medico'],
        '/admin/dashboard' => ['administrador'],
        '/admin/medicos' => ['administrador'],
        '/admin/usuarios' => ['administrador'],
        '/recepcion/pacientes' => ['recepcionista', 'administrador'],
        '/recepcion/pacientes/create' => ['recepcionista', 'administrador'],
        '/recepcion/pacientes/{paciente}/citas/create' => ['recepcionista', 'administrador'],
        '/admin/auditoria' => ['administrador'],
        '/admin/clinica' => ['administrador'],
        '/admin/medicos/{medico}/horarios' => ['administrador'],
        // Público: cualquiera entra, con o sin sesión
        '/directorio' => ['invitado', 'paciente', 'medico', 'recepcionista', 'administrador'],
    ];

    private int $medicoId;

    private int $pacienteId;

    private const ROLES = ['invitado', 'paciente', 'medico', 'recepcionista', 'administrador'];

    /** @var array<string, Usuario> */
    private array $usuarios = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();

        $this->medicoId = $this->crearMedico($this->crearEspecialidad('Cardiología'), 'medico@x.com');
        $pacienteUsuario = $this->crearUsuario(Rol::PACIENTE, 'paciente@x.com');
        $this->pacienteId = $this->crearPaciente('11111111');
        DB::table('pacientes')->where('id', $this->pacienteId)->update(['usuario_id' => $pacienteUsuario]);

        $this->usuarios = [
            'paciente' => Usuario::find($pacienteUsuario),
            'medico' => Usuario::find(DB::table('medicos')->where('id', $this->medicoId)->value('usuario_id')),
            'recepcionista' => Usuario::find($this->crearUsuario(Rol::RECEPCIONISTA, 'recepcion@x.com')),
            'administrador' => Usuario::find($this->crearUsuario(Rol::ADMINISTRADOR, 'admin@x.com')),
        ];
    }

    public static function combinaciones(): iterable
    {
        foreach (self::MATRIZ as $ruta => $permitidos) {
            foreach (self::ROLES as $rol) {
                yield "{$rol} → {$ruta}" => [$rol, $ruta, in_array($rol, $permitidos, true)];
            }
        }
    }

    #[DataProvider('combinaciones')]
    public function test_acceso_por_rol(string $rol, string $ruta, bool $permitido): void
    {
        if ($rol !== 'invitado') {
            $this->actingAs($this->usuarios[$rol]);
        }

        $respuesta = $this->get(str_replace(['{medico}', '{paciente}'], [$this->medicoId, $this->pacienteId], $ruta));

        if ($permitido) {
            $respuesta->assertOk();
        } else {
            $respuesta->assertRedirect('/login');
        }
    }

    public function test_el_login_lleva_a_la_recepcionista_a_su_modulo(): void
    {
        $this->post('/login', ['correo' => 'recepcion@x.com', 'contrasena' => 'secreto123'])
            ->assertRedirect('/recepcion/pacientes');
    }
}
