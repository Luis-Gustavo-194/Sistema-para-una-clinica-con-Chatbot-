<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\EsquemaClinica;
use App\Models\Medico;
use App\Services\CitaService;
use App\Services\DisponibilidadService;

class DisponibilidadTest extends TestCase
{
    use EsquemaClinica;

    private Medico $medico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        // Jueves 15/10/2026
        $this->travelTo(Carbon::parse('2026-10-15 09:00:00'));

        $this->medico = Medico::find($this->crearMedico($this->crearEspecialidad('Cardiología'), 'doc@x.com'));
        // Viernes (5): 08:00-10:00 cada 30 min en C-101 · Lunes (1): 15:00-16:00 cada 20 min
        DB::table('horarios_medicos')->insert([
            ['medico_id' => $this->medico->id, 'dia_semana' => 5, 'hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00', 'duracion_consulta_minutos' => 30, 'consultorio' => 'C-101'],
            ['medico_id' => $this->medico->id, 'dia_semana' => 1, 'hora_inicio' => '15:00:00', 'hora_fin' => '16:00:00', 'duracion_consulta_minutos' => 20, 'consultorio' => 'C-202'],
        ]);
    }

    private function inicios(array $turnos): array
    {
        return array_column($turnos, 'inicio');
    }

    public function test_genera_turnos_segun_horario_y_duracion(): void
    {
        $viernes = DisponibilidadService::turnosDelDia($this->medico, Carbon::parse('2026-10-16'));
        $this->assertSame(['08:00:00', '08:30:00', '09:00:00', '09:30:00'], $this->inicios($viernes));
        $this->assertSame('08:30:00', $viernes[0]['fin']);
        $this->assertSame('C-101', $viernes[0]['consultorio']);

        $lunes = DisponibilidadService::turnosDelDia($this->medico, Carbon::parse('2026-10-19'));
        $this->assertSame(['15:00:00', '15:20:00', '15:40:00'], $this->inicios($lunes));

        // Sábado: no atiende
        $this->assertSame([], DisponibilidadService::turnosDelDia($this->medico, Carbon::parse('2026-10-17')));
    }

    public function test_excluye_citas_que_se_cruzan_pero_no_las_canceladas(): void
    {
        $paciente = $this->crearPaciente('11111111');
        $this->crearCita($paciente, $this->medico->id, '2026-10-16', '08:30:00');                 // 08:30-09:00
        $this->crearCita($paciente, $this->medico->id, '2026-10-16', '09:15:00', 'Pendiente');     // 09:15-09:45 se cruza con 09:00 y 09:30
        $this->crearCita($paciente, $this->medico->id, '2026-10-16', '08:00:00', 'Cancelada');     // no bloquea

        $turnos = DisponibilidadService::turnosDelDia($this->medico, Carbon::parse('2026-10-16'));
        $this->assertSame(['08:00:00'], $this->inicios($turnos));
    }

    public function test_proximos_dias_desde_manana_y_excluir_cita(): void
    {
        $dias = DisponibilidadService::proximosDias($this->medico, 7);
        $this->assertSame(['2026-10-16', '2026-10-19'], array_keys($dias)); // viernes y lunes; hoy (jueves) no cuenta

        $cita = $this->crearCita($this->crearPaciente('11111111'), $this->medico->id, '2026-10-16', '08:00:00');
        $this->assertNotContains('08:00:00', $this->inicios(DisponibilidadService::proximosDias($this->medico, 7)['2026-10-16']));
        // Al reprogramar esa misma cita, su turno vuelve a estar disponible
        $this->assertContains('08:00:00', $this->inicios(DisponibilidadService::proximosDias($this->medico, 7, $cita)['2026-10-16']));
    }

    public function test_medico_sin_horarios_no_tiene_disponibilidad(): void
    {
        $sinHorario = Medico::find($this->crearMedico($this->medico->especialidad_id, 'otro@x.com'));

        $this->assertFalse(DisponibilidadService::tieneHorarios($sinHorario));
        $this->assertSame([], DisponibilidadService::proximosDias($sinHorario));
    }

    public function test_valida_turnos_enviados_por_formulario(): void
    {
        $this->assertNotNull(DisponibilidadService::esTurnoValido($this->medico, '2026-10-16', '09:00:00'));
        $this->assertNull(DisponibilidadService::esTurnoValido($this->medico, '2026-10-16', '09:10:00')); // no es un turno
        $this->assertNull(DisponibilidadService::esTurnoValido($this->medico, '2026-10-17', '09:00:00')); // sábado
        $this->assertNull(DisponibilidadService::esTurnoValido($this->medico, '2026-10-15', '09:00:00')); // hoy
        $this->assertNull(DisponibilidadService::esTurnoValido($this->medico, '2026-11-20', '09:00:00')); // fuera de los 14 días
        $this->assertNull(DisponibilidadService::esTurnoValido($this->medico, '2026-02-31', '09:00:00')); // fecha inválida
        $this->assertNull(DisponibilidadService::esTurnoValido($this->medico, 'mañana', '09:00:00'));
    }

    public function test_cita_service_detecta_solapamientos_no_solo_misma_hora(): void
    {
        $paciente = $this->crearPaciente('11111111');
        $otro = $this->crearPaciente('22222222');
        $this->crearCita($paciente, $this->medico->id, '2026-10-16', '08:00:00'); // 08:00-08:30

        $this->expectException(\DomainException::class);
        CitaService::guardar([
            'paciente_id' => $otro, 'medico_id' => $this->medico->id, 'fecha' => '2026-10-16',
            'hora_inicio' => '08:15:00', 'hora_fin' => '08:45:00',
        ]);
    }
}
