<?php

namespace Tests\Feature\Recepcion;

use App\Models\Cita;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\CitaConfirmada;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// Recepción reserva y cancela con el mismo CitaService que la web y el chatbot (canal 'recepcion')
class ReservaRecepcionTest extends TestCase
{
    use EsquemaClinica;

    private Usuario $recepcionista;

    private Usuario $usuarioPaciente;

    private int $paciente;

    private int $especialidad;

    private int $medico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->travelTo(Carbon::parse('2026-10-15 09:00:00')); // jueves

        $this->especialidad = $this->crearEspecialidad('Cardiología');
        $this->medico = $this->crearMedico($this->especialidad, 'doc@x.com', 'Ramírez');
        DB::table('horarios_medicos')->insert(['medico_id' => $this->medico, 'dia_semana' => 5, 'hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00', 'duracion_consulta_minutos' => 30]);

        $this->usuarioPaciente = Usuario::find($this->crearUsuario(Rol::PACIENTE, 'rosa@correo.com'));
        $this->paciente = DB::table('pacientes')->insertGetId(['usuario_id' => $this->usuarioPaciente->id, 'dni' => '76543210', 'nombres' => 'Rosa', 'apellidos' => 'Quispe']);

        $this->recepcionista = Usuario::find($this->crearUsuario(Rol::RECEPCIONISTA, 'recepcion@x.com'));
        $this->actingAs($this->recepcionista);
        Notification::fake();
    }

    public function test_reserva_para_el_paciente_con_canal_recepcion(): void
    {
        $this->get("/recepcion/pacientes/{$this->paciente}")->assertOk()->assertSee(route('recepcion.pacientes.citas.create', $this->paciente));
        $this->get("/recepcion/pacientes/{$this->paciente}/citas/create?especialidad={$this->especialidad}&medico={$this->medico}")
            ->assertOk()
            ->assertSee('Para Rosa Quispe')
            ->assertSee('value="2026-10-16|08:00:00"', false);

        $this->post("/recepcion/pacientes/{$this->paciente}/citas", ['medico' => $this->medico, 'turno' => '2026-10-16|08:00:00'])
            ->assertRedirect("/recepcion/pacientes/{$this->paciente}")
            ->assertSessionHas('exito');

        $cita = Cita::first();
        $this->assertSame([$this->paciente, 'recepcion', 'Pendiente'], [(int) $cita->paciente_id, $cita->canal, $cita->estado]);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $this->recepcionista->id,
            'accion' => "Agendó la cita #{$cita->id} para el paciente #{$this->paciente} desde recepción",
            'tabla_afectada' => 'citas',
        ]);
        Notification::assertSentTo($this->usuarioPaciente, CitaConfirmada::class);
        $this->get("/recepcion/pacientes/{$this->paciente}")->assertSee('Recepción');
    }

    public function test_conflicto_vuelve_con_alternativas(): void
    {
        $this->crearCita($this->crearPaciente('22222222'), $this->medico, '2026-10-16', '08:00:00');

        $this->post("/recepcion/pacientes/{$this->paciente}/citas", ['medico' => $this->medico, 'turno' => '2026-10-16|08:00:00'])
            ->assertRedirect("/recepcion/pacientes/{$this->paciente}/citas/create?especialidad={$this->especialidad}&medico={$this->medico}")
            ->assertSessionHasErrors('turno');

        $this->assertSame('08:30:00', session('alternativas')[0]['inicio']);
        $this->assertSame(1, Cita::count());
    }

    public function test_recepcion_cancela_aunque_falten_menos_de_24_horas(): void
    {
        $cita = $this->crearCita($this->paciente, $this->medico, '2026-10-16', '08:00:00'); // en 23 h

        $this->patch("/recepcion/citas/{$cita}/cancelar")
            ->assertRedirect("/recepcion/pacientes/{$this->paciente}")
            ->assertSessionHas('exito');

        $this->assertSame('Cancelada', Cita::find($cita)->estado);
        $this->assertDatabaseHas('auditorias', ['accion' => "Canceló la cita #{$cita} del paciente #{$this->paciente} desde recepción"]);
    }

    public function test_paciente_y_medico_no_reservan_por_recepcion(): void
    {
        $medico = Usuario::find(DB::table('medicos')->where('id', $this->medico)->value('usuario_id'));

        $this->actingAs($this->usuarioPaciente)->post("/recepcion/pacientes/{$this->paciente}/citas", ['medico' => $this->medico, 'turno' => '2026-10-16|08:00:00'])->assertRedirect('/login');
        $this->actingAs($medico)->post("/recepcion/pacientes/{$this->paciente}/citas", ['medico' => $this->medico, 'turno' => '2026-10-16|08:00:00'])->assertRedirect('/login');

        $this->assertSame(0, Cita::count());
    }
}
