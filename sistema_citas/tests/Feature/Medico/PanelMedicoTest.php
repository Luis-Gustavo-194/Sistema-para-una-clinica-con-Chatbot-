<?php

namespace Tests\Feature\Medico;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\EsquemaClinica;
use App\Models\Cita;
use App\Models\Medico;
use App\Models\Rol;
use App\Models\Usuario;

class PanelMedicoTest extends TestCase
{
    use EsquemaClinica;

    private Medico $medico;
    private Medico $otroMedico;
    private int $paciente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->travelTo(Carbon::parse('2026-10-15 09:00:00')); // jueves

        $cardio = $this->crearEspecialidad('Cardiología');
        $this->medico = Medico::find($this->crearMedico($cardio, 'doc@x.com', 'Ramírez'));
        $this->otroMedico = Medico::find($this->crearMedico($cardio, 'otro@x.com', 'Salazar'));
        $this->paciente = DB::table('pacientes')->insertGetId([
            'dni' => '11111111', 'nombres' => 'Juan', 'apellidos' => 'Pérez', 'telefono' => '987654321', 'fecha_nacimiento' => '1990-05-20',
        ]);

        $this->actingAs($this->medico->usuario);
    }

    public function test_agenda_de_hoy_y_kpis(): void
    {
        $this->crearCita($this->paciente, $this->medico->id, '2026-10-15', '09:00:00', 'Pendiente');
        $this->crearCita($this->paciente, $this->medico->id, '2026-10-15', '10:00:00', 'Cancelada'); // no aparece
        $this->crearCita($this->paciente, $this->medico->id, '2026-10-16', '09:00:00', 'Pendiente');
        $this->crearCita($this->paciente, $this->medico->id, '2026-10-05', '09:00:00', 'Atendida');
        $this->crearCita($this->paciente, $this->medico->id, '2026-10-06', '09:00:00', 'No Asistio');
        $this->crearCita($this->paciente, $this->otroMedico->id, '2026-10-15', '11:00:00'); // de otro médico

        $respuesta = $this->get('/dashboard-medico')->assertOk()
            ->assertSee('Juan Pérez')
            ->assertSee('36') // edad al 15/10/2026
            ->assertSee('987654321')
            ->assertSee('Aún no configuraste tus horarios');

        $this->assertCount(1, $respuesta->viewData('agenda'));
        $kpis = $respuesta->viewData('kpis');
        $this->assertSame(1, $kpis['citas_hoy']);
        $this->assertSame(1, $kpis['por_atender_hoy']);
        $this->assertSame(2, $kpis['citas_semana']);   // 15 y 16 (lunes 12 a domingo 18)
        $this->assertSame(1, $kpis['atendidas_mes']);
        $this->assertSame(50.0, $kpis['tasa_inasistencia']);
        $this->assertSame(1, $kpis['pacientes_mes']);
    }

    public function test_no_puede_ver_ni_modificar_citas_de_otro_medico(): void
    {
        $ajena = $this->crearCita($this->paciente, $this->otroMedico->id, '2026-10-15', '11:00:00');

        $this->get("/medico/citas/{$ajena}")->assertNotFound();
        $this->patch("/medico/citas/{$ajena}/estado", ['estado' => 'No Asistio'])->assertNotFound();
        $this->post("/medico/citas/{$ajena}/atencion", ['diagnostico' => 'x'])->assertNotFound();
        $this->assertSame('Pendiente', Cita::find($ajena)->estado);
    }

    public function test_transiciones_de_estado(): void
    {
        $manana = $this->crearCita($this->paciente, $this->medico->id, '2026-10-16', '09:00:00');
        $hoy = $this->crearCita($this->paciente, $this->medico->id, '2026-10-15', '09:00:00');
        $cancelada = $this->crearCita($this->paciente, $this->medico->id, '2026-10-15', '10:00:00', 'Cancelada');

        // Confirmar una futura: sí. Marcar "No asistió" una futura: no.
        $this->patch("/medico/citas/{$manana}/estado", ['estado' => 'Confirmada'])->assertSessionHas('exito');
        $this->assertSame('Confirmada', Cita::find($manana)->estado);
        $this->patch("/medico/citas/{$manana}/estado", ['estado' => 'No Asistio'])->assertSessionHasErrors('estado');

        // "No asistió" en una de hoy: sí. Sobre una cancelada: no. Estado inventado: no.
        $this->patch("/medico/citas/{$hoy}/estado", ['estado' => 'No Asistio'])->assertSessionHas('exito');
        $this->assertSame('No Asistio', Cita::find($hoy)->estado);
        $this->patch("/medico/citas/{$cancelada}/estado", ['estado' => 'No Asistio'])->assertSessionHasErrors('estado');
        $this->patch("/medico/citas/{$manana}/estado", ['estado' => 'Atendida'])->assertSessionHasErrors('estado');

        $this->assertDatabaseHas('auditorias', ['tabla_afectada' => 'citas', 'registro_id' => $manana]);
    }

    public function test_registrar_atencion_crea_historia_y_marca_atendida(): void
    {
        $cita = $this->crearCita($this->paciente, $this->medico->id, '2026-10-15', '09:00:00', 'Confirmada');

        $this->post("/medico/citas/{$cita}/atencion", ['diagnostico' => ''])->assertSessionHasErrors('diagnostico');

        $this->post("/medico/citas/{$cita}/atencion", [
            'diagnostico' => 'Hipertensión leve', 'tratamiento' => 'Dieta baja en sodio', 'receta_medica' => 'Losartán 50 mg',
        ])->assertRedirect("/medico/citas/{$cita}");

        $this->assertSame('Atendida', Cita::find($cita)->estado);
        $this->assertDatabaseHas('historias_clinicas', ['cita_id' => $cita, 'diagnostico' => 'Hipertensión leve', 'receta_medica' => 'Losartán 50 mg']);

        // Corregir la historia no crea otra
        $this->post("/medico/citas/{$cita}/atencion", ['diagnostico' => 'Hipertensión estadio 1']);
        $this->assertSame(1, DB::table('historias_clinicas')->where('cita_id', $cita)->count());
        $this->assertDatabaseHas('historias_clinicas', ['cita_id' => $cita, 'diagnostico' => 'Hipertensión estadio 1']);
    }

    public function test_no_se_atiende_una_cita_futura_ni_cancelada(): void
    {
        $futura = $this->crearCita($this->paciente, $this->medico->id, '2026-10-20', '09:00:00');
        $cancelada = $this->crearCita($this->paciente, $this->medico->id, '2026-10-14', '09:00:00', 'Cancelada');

        $this->post("/medico/citas/{$futura}/atencion", ['diagnostico' => 'x'])->assertSessionHasErrors('cita');
        $this->post("/medico/citas/{$cancelada}/atencion", ['diagnostico' => 'x'])->assertSessionHasErrors('cita');
        $this->assertDatabaseCount('historias_clinicas', 0);
    }

    public function test_detalle_muestra_historial_del_paciente(): void
    {
        $anterior = $this->crearCita($this->paciente, $this->otroMedico->id, '2026-09-10', '09:00:00', 'Atendida');
        DB::table('historias_clinicas')->insert(['cita_id' => $anterior, 'diagnostico' => 'Gastritis crónica']);
        $actual = $this->crearCita($this->paciente, $this->medico->id, '2026-10-15', '09:00:00');

        $this->get("/medico/citas/{$actual}")->assertOk()
            ->assertSee('Gastritis crónica')
            ->assertSee('Salazar')
            ->assertSee('Registrar atención y marcar');
    }

    public function test_lista_de_citas_con_pestanas_y_filtros(): void
    {
        $this->crearCita($this->paciente, $this->medico->id, '2026-10-20', '09:00:00', 'Confirmada');
        $this->crearCita($this->paciente, $this->medico->id, '2026-10-01', '09:00:00', 'Atendida');

        $proximas = $this->get('/medico/citas')->assertOk()->viewData('citas');
        $this->assertSame(['2026-10-20'], $proximas->pluck('fecha')->all());

        $historial = $this->get('/medico/citas?pestana=historial')->viewData('citas');
        $this->assertSame(['2026-10-01'], $historial->pluck('fecha')->all());

        $this->assertCount(0, $this->get('/medico/citas?estado=Pendiente')->viewData('citas'));
    }

    public function test_guardar_horarios_y_avisar_citas_fuera(): void
    {
        // Cita del lunes 19 a las 15:00: quedará fuera de un horario solo de mañana
        $this->crearCita($this->paciente, $this->medico->id, '2026-10-19', '15:00:00');

        $this->get('/medico/horarios')->assertOk()->assertSee('Lunes');

        $this->put('/medico/horarios', ['dias' => [
            1 => ['activo' => '1', 'inicio' => '08:00', 'fin' => '12:00', 'inicio2' => '14:00', 'fin2' => '14:30', 'duracion' => '30', 'consultorio' => 'C-5'],
            3 => ['activo' => '1', 'inicio' => '09:00', 'fin' => '11:00', 'inicio2' => '', 'fin2' => '', 'duracion' => '20', 'consultorio' => ''],
            5 => ['inicio' => '09:00', 'fin' => '11:00', 'duracion' => '30'], // sin "activo": se ignora
        ]])->assertRedirect('/medico/horarios')
            ->assertSessionHas('aviso', fn ($aviso) => str_contains($aviso, '1 cita(s) futura(s)'));

        $horarios = DB::table('horarios_medicos')->where('medico_id', $this->medico->id)->orderBy('dia_semana')->orderBy('hora_inicio')->get();
        $this->assertCount(3, $horarios);
        $this->assertSame([1, 1, 3], $horarios->pluck('dia_semana')->map(fn ($d) => (int) $d)->all());
        $this->assertSame('14:00:00', $horarios[1]->hora_inicio);
        $this->assertSame(20, (int) $horarios[2]->duracion_consulta_minutos);
    }

    public function test_horarios_invalidos(): void
    {
        $this->put('/medico/horarios', ['dias' => [
            1 => ['activo' => '1', 'inicio' => '12:00', 'fin' => '08:00', 'duracion' => '30'],
            2 => ['activo' => '1', 'inicio' => '08:00', 'fin' => '12:00', 'inicio2' => '11:00', 'fin2' => '13:00', 'duracion' => '5'],
        ]])->assertSessionHasErrors(['dias.1.fin', 'dias.2.inicio2', 'dias.2.duracion']);

        $this->assertDatabaseCount('horarios_medicos', 0);
    }

    public function test_solo_medicos_entran_al_panel(): void
    {
        $paciente = Usuario::find($this->crearUsuario(Rol::PACIENTE, 'p@x.com'));
        $this->actingAs($paciente)->get('/dashboard-medico')->assertRedirect('/login');
        $this->actingAs($paciente)->get('/medico/horarios')->assertRedirect('/login');
    }
}
