<?php

namespace Tests\Feature\Paciente;

use App\Models\Cita;
use App\Models\Medico;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\CitaConfirmada;
use App\Services\DisponibilidadService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// "Mis citas" con filtro por estado, cancelación con anticipación mínima,
// conflicto de horario con alternativas y correo de confirmación
class MisCitasYConflictosTest extends TestCase
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
        // Viernes de 08:00 a 10:00, turnos de 30 min
        DB::table('horarios_medicos')->insert(['medico_id' => $this->medico, 'dia_semana' => 5, 'hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00', 'duracion_consulta_minutos' => 30]);

        $this->usuario = Usuario::find($this->crearUsuario(Rol::PACIENTE, 'juan@x.com'));
        $this->paciente = DB::table('pacientes')->insertGetId(['usuario_id' => $this->usuario->id, 'dni' => '11111111', 'nombres' => 'Juan', 'apellidos' => 'Pérez']);
        $this->otroPaciente = $this->crearPaciente('22222222', 'María');
        $this->actingAs($this->usuario);
    }

    public function test_mis_citas_filtra_por_estado(): void
    {
        $this->crearCita($this->paciente, $this->medico, '2026-10-23', '08:00:00', 'Pendiente');
        $this->crearCita($this->paciente, $this->medico, '2026-10-30', '08:00:00', null); // NULL cuenta como Pendiente
        $this->crearCita($this->paciente, $this->medico, '2026-10-01', '08:00:00', 'Atendida');
        $this->crearCita($this->paciente, $this->medico, '2026-10-02', '08:00:00', 'Cancelada');

        $todas = $this->get('/paciente/citas')->assertOk();
        $this->assertCount(2, $todas->viewData('proximas'));
        $this->assertCount(2, $todas->viewData('historial'));

        $pendientes = $this->get('/paciente/citas?estado=Pendiente')->assertOk();
        $this->assertCount(2, $pendientes->viewData('proximas'));
        $this->assertCount(0, $pendientes->viewData('historial'));

        $atendidas = $this->get('/paciente/citas?estado=Atendida')->assertOk()->assertSee('No tienes citas próximas con ese estado.');
        $this->assertSame(['Atendida'], $atendidas->viewData('historial')->pluck('estado')->values()->all());

        // Un estado desconocido se ignora
        $this->assertNull($this->get('/paciente/citas?estado=Inventado')->viewData('estado'));
    }

    public function test_no_cancela_con_menos_de_24_horas_y_si_con_mas(): void
    {
        $manana = $this->crearCita($this->paciente, $this->medico, '2026-10-16', '08:00:00'); // en 23 h
        $proxSemana = $this->crearCita($this->paciente, $this->medico, '2026-10-23', '08:00:00');

        $this->get('/paciente/citas')->assertSee('Solo se puede cancelar con 24 h de anticipación. Comunícate con recepción.');

        $this->patch("/paciente/citas/{$manana}/cancelar")->assertRedirect('/paciente/citas')
            ->assertSessionHasErrors(['cita' => 'Las citas solo se pueden cancelar con al menos 24 horas de anticipación. Comunícate con recepción.']);
        $this->assertSame('Pendiente', Cita::find($manana)->estado);

        $this->patch("/paciente/citas/{$proxSemana}/cancelar")->assertSessionHas('exito');
        $this->assertSame('Cancelada', Cita::find($proxSemana)->estado);

        // El turno quedó libre: otro paciente lo puede tomar
        $this->assertContains('08:00:00', array_column(DisponibilidadService::proximosDias(Medico::find($this->medico))['2026-10-23'], 'inicio'));
    }

    public function test_conflicto_recarga_los_horarios_y_ofrece_alternativas(): void
    {
        // Mientras Juan elegía, María tomó el viernes 16 a las 08:30
        $this->crearCita($this->otroPaciente, $this->medico, '2026-10-16', '08:30:00');

        $respuesta = $this->post('/paciente/citas', ['medico' => $this->medico, 'turno' => '2026-10-16|08:30:00']);

        $respuesta->assertRedirect("/paciente/citas/nueva?especialidad={$this->especialidad}&medico={$this->medico}")
            ->assertSessionHasErrors(['turno' => 'El horario elegido ya no está disponible. Te mostramos los horarios libres más cercanos.']);
        $this->assertSame(
            [['2026-10-16', '08:00:00'], ['2026-10-16', '09:00:00'], ['2026-10-16', '09:30:00']],
            array_map(fn ($a) => [$a['fecha'], $a['inicio']], session('alternativas'))
        );

        // El formulario muestra los horarios libres actualizados (sin las 08:30) y las alternativas destacadas
        $this->get("/paciente/citas/nueva?especialidad={$this->especialidad}&medico={$this->medico}")
            ->assertOk()
            ->assertSee('Horarios cercanos libres')
            ->assertDontSee('value="2026-10-16|08:30:00"', false)
            ->assertSee('value="2026-10-16|09:00:00"', false);
    }

    public function test_reserva_envia_el_correo_de_confirmacion(): void
    {
        Notification::fake();

        $this->post('/paciente/citas', ['medico' => $this->medico, 'turno' => '2026-10-16|09:00:00'])->assertSessionHas('exito');

        $cita = Cita::first();
        $this->assertSame('web', $cita->canal);
        Notification::assertSentTo($this->usuario, CitaConfirmada::class, function (CitaConfirmada $notificacion) use ($cita) {
            $correo = $notificacion->toMail($this->usuario);

            return $notificacion->cita->is($cita) && ! $notificacion->reprogramada
                && str_starts_with($correo->subject, 'Cita confirmada')
                && in_array('**Fecha:** Viernes 16/10/2026 a las 9:00 AM', $correo->introLines, true);
        });
    }

    public function test_el_correo_de_confirmacion_es_opcional(): void
    {
        Notification::fake();
        config(['clinica.correo_confirmacion' => false]);

        $this->post('/paciente/citas', ['medico' => $this->medico, 'turno' => '2026-10-16|09:00:00'])->assertSessionHas('exito');

        Notification::assertNothingSent();
    }
}
