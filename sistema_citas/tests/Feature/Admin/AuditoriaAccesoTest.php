<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AuditoriaController;
use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// HU17: registro de accesos y panel de auditoría (solo Administrador)
class AuditoriaAccesoTest extends TestCase
{
    use EsquemaClinica;

    private Usuario $admin;

    private Usuario $paciente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->admin = Usuario::find($this->crearUsuario(Rol::ADMINISTRADOR, 'admin@x.com'));
        $this->paciente = Usuario::find($this->crearUsuario(Rol::PACIENTE, 'rosa@correo.com'));
    }

    private function login(string $correo, string $contrasena)
    {
        app('auth')->forgetGuards();

        return $this->post('/login', ['correo' => $correo, 'contrasena' => $contrasena]);
    }

    public function test_registra_inicio_y_cierre_de_sesion(): void
    {
        $this->login('rosa@correo.com', 'secreto123')->assertRedirect('/paciente');
        $this->get('/logout');

        $this->assertDatabaseHas('auditorias', ['usuario_id' => $this->paciente->id, 'accion' => 'Inició sesión', 'tabla_afectada' => 'usuarios', 'canal' => 'web']);
        $this->assertDatabaseHas('auditorias', ['usuario_id' => $this->paciente->id, 'accion' => 'Cerró sesión']);
    }

    public function test_registra_los_intentos_fallidos(): void
    {
        $this->login('rosa@correo.com', 'mala');
        $this->login('nadie@correo.com', 'mala');
        DB::table('usuarios')->insert(['rol_id' => Rol::PACIENTE, 'correo' => 'inactivo@correo.com', 'contrasena' => bcrypt('secreto123'), 'estado' => 0]);
        $this->login('inactivo@correo.com', 'secreto123');

        $this->assertDatabaseHas('auditorias', ['usuario_id' => $this->paciente->id, 'accion' => 'Intento fallido de inicio de sesión']);
        // Correo inexistente: sin usuario y con el correo enmascarado (HU18)
        $this->assertDatabaseHas('auditorias', ['usuario_id' => null, 'accion' => 'Intento fallido de inicio de sesión (n***@correo.com)']);
        $this->assertDatabaseHas('auditorias', ['accion' => 'Intento de inicio de sesión con usuario desactivado']);
    }

    public function test_registra_el_bloqueo_y_los_intentos_con_la_cuenta_bloqueada(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->login('rosa@correo.com', 'mala');
        }

        $this->assertSame(5, Auditoria::where('accion', 'Intento fallido de inicio de sesión')->count());
        $this->assertSame(1, Auditoria::where('accion', 'Cuenta bloqueada por intentos fallidos')->count());
        $this->assertSame(1, Auditoria::where('accion', 'Intento de inicio de sesión con cuenta bloqueada')->count());
    }

    public function test_solo_el_administrador_ve_la_auditoria(): void
    {
        $this->get('/admin/auditoria')->assertRedirect('/login');
        $this->actingAs($this->paciente)->get('/admin/auditoria')->assertRedirect('/login');
        $this->actingAs(Usuario::find($this->crearUsuario(Rol::RECEPCIONISTA, 'recepcion@x.com')))->get('/admin/auditoria')->assertRedirect('/login');

        $this->actingAs($this->admin)->get('/admin/auditoria')->assertOk()->assertSee('Auditoría');
    }

    public function test_resalta_los_intentos_fallidos(): void
    {
        $this->login('rosa@correo.com', 'mala');
        $this->login('rosa@correo.com', 'secreto123');

        $html = $this->actingAs($this->admin)->get('/admin/auditoria')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-fallo'));
        $this->assertSame(1, substr_count($html, '⚠ Fallo'));
        $this->assertStringContainsString('Inició sesión', $html);
    }

    // Datos con fechas, canales y tablas distintos para comprobar los filtros
    private function sembrarBitacora(): void
    {
        $filas = [
            ['usuario_id' => $this->paciente->id, 'accion' => 'Inició sesión', 'tabla_afectada' => 'usuarios', 'canal' => 'web', 'fecha_hora' => '2026-10-10 08:00:00'],
            ['usuario_id' => $this->paciente->id, 'accion' => 'Intento fallido de inicio de sesión', 'tabla_afectada' => 'usuarios', 'canal' => 'web', 'fecha_hora' => '2026-10-10 07:59:00'],
            ['usuario_id' => null, 'accion' => 'Intento fallido de inicio de sesión (x***@correo.com)', 'tabla_afectada' => 'usuarios', 'canal' => 'web', 'fecha_hora' => '2026-10-12 10:00:00'],
            ['usuario_id' => $this->paciente->id, 'accion' => 'Agendó la cita #5 por el chatbot', 'tabla_afectada' => 'citas', 'canal' => 'chatbot', 'fecha_hora' => '2026-10-12 11:00:00'],
            ['usuario_id' => $this->admin->id, 'accion' => 'Creó la especialidad Pediatría', 'tabla_afectada' => 'especialidades', 'canal' => 'web', 'fecha_hora' => '2026-10-14 09:00:00'],
            ['usuario_id' => $this->admin->id, 'accion' => 'Cuenta bloqueada por intentos fallidos', 'tabla_afectada' => 'usuarios', 'canal' => 'web', 'fecha_hora' => '2026-10-14 09:30:00'],
        ];
        DB::table('auditorias')->insert($filas);
    }

    public static function filtros(): array
    {
        return [
            'sin filtros' => [[], 6],
            'solo fallos' => [['fallos' => '1'], 3],
            'tabla citas' => [['tabla' => 'citas'], 1],
            'canal chatbot' => [['canal' => 'chatbot'], 1],
            'rango de fechas' => [['desde' => '2026-10-12', 'hasta' => '2026-10-12'], 2],
            'buscar por correo del usuario' => [['buscar' => 'rosa@'], 3],
            'buscar por acción' => [['buscar' => 'especialidad'], 1],
            'fallos dentro de un rango' => [['fallos' => '1', 'desde' => '2026-10-11'], 2],
        ];
    }

    // Lo que se muestra coincide con la consulta en la BD para cada combinación de filtros
    #[DataProvider('filtros')]
    public function test_lo_mostrado_coincide_con_la_bd(array $filtros, int $esperados): void
    {
        $this->sembrarBitacora();

        $respuesta = $this->actingAs($this->admin)->get(route('admin.auditoria.index', $filtros))->assertOk();
        $mostrados = $respuesta->viewData('registros');

        $enBd = AuditoriaController::filtrar(Auditoria::query(), $filtros)->orderByDesc('fecha_hora')->orderByDesc('id')->pluck('id')->all();

        $this->assertSame($esperados, $mostrados->total());
        $this->assertSame($enBd, $mostrados->pluck('id')->all());
        $this->assertSame(
            $mostrados->getCollection()->filter->esFalloDeAcceso()->count(),
            substr_count($respuesta->getContent(), 'data-fallo')
        );
    }

    public function test_el_contador_de_fallos_cuenta_solo_las_ultimas_24_horas(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00'));
        $this->sembrarBitacora();

        $this->actingAs($this->admin)->get('/admin/auditoria')->assertViewHas('fallos24h', 1);
    }

    public function test_los_datos_sensibles_se_muestran_enmascarados(): void
    {
        $this->login('alguien.real@correo.com', 'mala');

        $this->actingAs($this->admin)->get('/admin/auditoria')
            ->assertSee('Intento fallido de inicio de sesión (a***@correo.com)')
            ->assertDontSee('alguien.real@correo.com');
    }

    public function test_valida_los_filtros(): void
    {
        $this->actingAs($this->admin)->get('/admin/auditoria?tabla=inventada&desde=2026-10-10&hasta=2026-10-01')
            ->assertSessionHasErrors(['tabla', 'hasta']);
    }
}
