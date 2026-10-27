<?php

namespace Tests\Feature\Paciente;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Concerns\EsquemaClinica;
use App\Models\Cita;
use App\Models\Rol;
use App\Models\Usuario;

class PanelPacienteTest extends TestCase
{
    use EsquemaClinica;

    private Usuario $usuario;
    private int $paciente;
    private int $otroPaciente;
    private int $especialidad;
    private int $medico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->travelTo(Carbon::parse('2026-10-15 09:00:00')); // jueves

        $this->especialidad = $this->crearEspecialidad('Cardiología');
        $this->medico = $this->crearMedico($this->especialidad, 'doc@x.com', 'Ramírez');
        // Atiende viernes (5) de 08:00 a 10:00, turnos de 30 min, consultorio C-101
        DB::table('horarios_medicos')->insert(['medico_id' => $this->medico, 'dia_semana' => 5, 'hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00', 'duracion_consulta_minutos' => 30, 'consultorio' => 'C-101']);

        $this->usuario = Usuario::find($this->crearUsuario(Rol::PACIENTE, 'juan@x.com', 1, 'ClaveActual1'));
        $this->paciente = DB::table('pacientes')->insertGetId(['usuario_id' => $this->usuario->id, 'dni' => '11111111', 'nombres' => 'Juan', 'apellidos' => 'Pérez', 'fecha_nacimiento' => '1990-01-01']);
        $this->otroPaciente = $this->crearPaciente('22222222', 'María');

        $this->actingAs($this->usuario);
    }

    public function test_inicio_muestra_la_proxima_cita_con_consultorio(): void
    {
        $this->crearCita($this->paciente, $this->medico, '2026-10-23', '09:00:00');
        $this->crearCita($this->paciente, $this->medico, '2026-10-16', '08:30:00', 'Confirmada'); // la más próxima
        $this->crearCita($this->paciente, $this->medico, '2026-10-01', '08:30:00', 'Atendida');

        $respuesta = $this->get('/paciente')->assertOk()->assertSee('Tu próxima cita')->assertSee('Consultorio C-101');
        $this->assertSame('2026-10-16', $respuesta->viewData('proxima')->fecha);
        $this->assertSame(1, $respuesta->viewData('diasFaltan'));
        $this->assertSame(['proximas' => 2, 'atendidas' => 1, 'inasistencias' => 0], $respuesta->viewData('resumen'));
    }

    public function test_selector_muestra_turnos_reales(): void
    {
        $this->get('/paciente/citas/nueva')->assertOk()->assertSee('Cardiología');

        $respuesta = $this->get("/paciente/citas/nueva?especialidad={$this->especialidad}&medico={$this->medico}")->assertOk();
        $dias = $respuesta->viewData('dias');
        $this->assertSame(['2026-10-16', '2026-10-23'], array_keys($dias)); // los viernes dentro de 14 días
        $this->assertCount(4, $dias['2026-10-16']);
        $respuesta->assertSee('value="2026-10-16|08:00:00"', false);
    }

    public function test_agendar_un_turno_valido(): void
    {
        $this->post('/paciente/citas', ['medico' => $this->medico, 'turno' => '2026-10-16|08:30:00'])
            ->assertRedirect('/paciente/citas')->assertSessionHas('exito');

        $this->assertDatabaseHas('citas', ['paciente_id' => $this->paciente, 'medico_id' => $this->medico, 'fecha' => '2026-10-16', 'hora_inicio' => '08:30:00', 'hora_fin' => '09:00:00', 'estado' => 'Pendiente']);
        $this->assertDatabaseHas('auditorias', ['usuario_id' => $this->usuario->id, 'tabla_afectada' => 'citas']);
    }

    public function test_rechaza_turnos_inventados_ocupados_o_de_medico_inactivo(): void
    {
        $this->crearCita($this->otroPaciente, $this->medico, '2026-10-16', '08:00:00');

        $this->post('/paciente/citas', ['medico' => $this->medico, 'turno' => '2026-10-16|08:00:00'])->assertSessionHasErrors('turno'); // ocupado
        $this->post('/paciente/citas', ['medico' => $this->medico, 'turno' => '2026-10-16|08:10:00'])->assertSessionHasErrors('turno'); // no es un turno
        $this->post('/paciente/citas', ['medico' => $this->medico, 'turno' => '2026-10-17|08:00:00'])->assertSessionHasErrors('turno'); // sábado
        $this->post('/paciente/citas', ['medico' => $this->medico, 'turno' => 'cualquier cosa'])->assertSessionHasErrors('turno');

        DB::table('usuarios')->where('correo', 'doc@x.com')->update(['estado' => 0]);
        $this->post('/paciente/citas', ['medico' => $this->medico, 'turno' => '2026-10-16|08:30:00'])->assertSessionHasErrors('turno');

        $this->assertSame(1, Cita::count());
    }

    public function test_reprogramar_y_cancelar_solo_citas_propias(): void
    {
        $mia = $this->crearCita($this->paciente, $this->medico, '2026-10-16', '08:00:00');
        $ajena = $this->crearCita($this->otroPaciente, $this->medico, '2026-10-16', '09:00:00');

        // Ajena: 404
        $this->get("/paciente/citas/{$ajena}/reprogramar")->assertNotFound();
        $this->put("/paciente/citas/{$ajena}", ['medico' => $this->medico, 'turno' => '2026-10-23|08:00:00'])->assertNotFound();
        $this->patch("/paciente/citas/{$ajena}/cancelar")->assertNotFound();

        // Reprogramar la propia: su turno actual se ofrece como libre
        $dias = $this->get("/paciente/citas/{$mia}/reprogramar")->assertOk()->viewData('dias');
        $this->assertContains('08:00:00', array_column($dias['2026-10-16'], 'inicio'));

        $this->put("/paciente/citas/{$mia}", ['medico' => $this->medico, 'turno' => '2026-10-23|09:30:00'])->assertRedirect('/paciente/citas');
        $this->assertDatabaseHas('citas', ['id' => $mia, 'fecha' => '2026-10-23', 'hora_inicio' => '09:30:00']);

        $this->patch("/paciente/citas/{$mia}/cancelar")->assertRedirect('/paciente/citas');
        $this->assertSame('Cancelada', Cita::find($mia)->estado);
        $this->assertSame('Pendiente', Cita::find($ajena)->estado);

        // Ya cancelada: no se puede volver a modificar
        $this->patch("/paciente/citas/{$mia}/cancelar")->assertRedirect('/paciente/citas')->assertSessionHasErrors('cita');
    }

    public function test_mis_citas_separa_proximas_e_historial(): void
    {
        $this->crearCita($this->paciente, $this->medico, '2026-10-16', '08:00:00');
        $this->crearCita($this->paciente, $this->medico, '2026-10-01', '08:00:00', 'Atendida');
        $this->crearCita($this->paciente, $this->medico, '2026-10-20', '08:00:00', 'Cancelada');

        $respuesta = $this->get('/paciente/citas')->assertOk();
        $this->assertCount(1, $respuesta->viewData('proximas'));
        $this->assertCount(2, $respuesta->viewData('historial'));
    }

    public function test_historia_clinica_solo_propia(): void
    {
        $mia = $this->crearCita($this->paciente, $this->medico, '2026-10-01', '08:00:00', 'Atendida');
        $ajena = $this->crearCita($this->otroPaciente, $this->medico, '2026-10-02', '08:00:00', 'Atendida');
        DB::table('historias_clinicas')->insert([
            ['cita_id' => $mia, 'diagnostico' => 'Diagnóstico de Juan', 'receta_medica' => 'Amoxicilina'],
            ['cita_id' => $ajena, 'diagnostico' => 'Diagnóstico de María', 'receta_medica' => null],
        ]);

        $this->get('/paciente/historia')->assertOk()->assertSee('Diagnóstico de Juan')->assertSee('Amoxicilina')->assertDontSee('Diagnóstico de María');
    }

    public function test_perfil_telefono_y_contrasena(): void
    {
        $this->get('/paciente/perfil')->assertOk()->assertSee('11111111');

        $this->put('/paciente/perfil', ['telefono' => 'abc'])->assertSessionHasErrors('telefono');
        $this->put('/paciente/perfil', ['telefono' => '987 654 321'])->assertSessionHas('exito');
        $this->assertDatabaseHas('pacientes', ['id' => $this->paciente, 'telefono' => '987 654 321']);

        $this->put('/paciente/contrasena', ['contrasena_actual' => 'incorrecta', 'contrasena' => 'NuevaClave9', 'contrasena_confirmation' => 'NuevaClave9'])
            ->assertSessionHasErrors(['contrasena_actual' => 'La contraseña actual no es correcta.']);
        $this->put('/paciente/contrasena', ['contrasena_actual' => 'ClaveActual1', 'contrasena' => 'NuevaClave9', 'contrasena_confirmation' => 'NuevaClave9'])
            ->assertSessionHas('exito');
        $this->assertTrue(Hash::check('NuevaClave9', $this->usuario->fresh()->contrasena));
    }

    public function test_solo_pacientes_entran_al_panel(): void
    {
        $medico = Usuario::where('correo', 'doc@x.com')->first();
        $this->actingAs($medico)->get('/paciente')->assertRedirect('/login');
        $this->actingAs($medico)->post('/paciente/citas', ['medico' => $this->medico, 'turno' => '2026-10-16|08:00:00'])->assertRedirect('/login');
    }
}
