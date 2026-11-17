<?php

namespace Tests\Feature\Chatbot;

use App\Models\Cita;
use App\Models\Usuario;
use Tests\Concerns\ConversaConChatbot;
use Tests\TestCase;

// El paciente puede volver atrás, cambiar un dato ya elegido o cancelar sin empezar de cero
class CorreccionTest extends TestCase
{
    use ConversaConChatbot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararChatbot();
    }

    // Llega al resumen de una cita con la Dra. Ríos mañana (17/11) a las 10:00
    private function llegarAlResumen(): array
    {
        return $this->chat('cita con la dra ríos mañana a las 10, dni 11111111');
    }

    public function test_atras_vuelve_a_la_pregunta_anterior(): void
    {
        $this->chat('quiero una cita, dni 11111111');
        $this->chat('Cardiología');
        $r = $this->chat('1');
        $this->assertSame(['10:00 AM', '11:30 AM'], $this->textos($r));

        $r = $this->chat('Atrás');
        $this->assertStringContainsString('elige otro médico', $r['respuesta']);
        $this->assertSame(['1'], $this->valores($r));

        $r = $this->chat('volver');
        $this->assertSame(['Cardiología', 'Pediatría'], $this->valores($r));
    }

    public function test_cambiar_medico_y_especialidad_desde_el_resumen(): void
    {
        $this->llegarAlResumen();

        $r = $this->chat('cambiar medico');
        $this->assertStringContainsString('Para Cardiología tenemos a los siguientes médicos', $r['respuesta']);

        $r = $this->chat('cambiar la especialidad');
        $this->assertSame(['Cardiología', 'Pediatría'], $this->valores($r));

        $this->chat('Pediatría');
        $r = $this->chat('2');
        $this->assertStringContainsString('Has seleccionado al Dr. Paz', $r['respuesta']);
    }

    public function test_correccion_libre_en_el_resumen_conserva_lo_que_no_se_cambio(): void
    {
        $this->llegarAlResumen();

        $r = $this->chat('mejor a las 11:30');
        $this->assertStringContainsString('Martes 17/11/2026', $r['respuesta']);
        $this->assertStringContainsString('11:30 AM', $r['respuesta']);

        $r = $this->chat('mejor el jueves');
        $this->assertStringContainsString('Jueves 19/11/2026', $r['respuesta']);
        $this->assertStringContainsString('11:30 AM', $r['respuesta']);

        $this->chat('sí');
        $this->assertDatabaseHas('citas', ['paciente_id' => 1, 'medico_id' => 1, 'fecha' => '2026-11-19', 'hora_inicio' => '11:30:00', 'estado' => 'Pendiente']);
    }

    public function test_cambiar_horario_ofrece_los_turnos_y_explica_que_se_puede_escribir(): void
    {
        $this->llegarAlResumen();

        $r = $this->chat('cambiar horario');

        $this->assertStringContainsString('el viernes a las 10', $r['respuesta']);
        $this->assertSame(['10:00 AM', '11:30 AM'], $this->textos($r));
    }

    public function test_cancelar_detiene_el_proceso_sin_guardar(): void
    {
        $this->llegarAlResumen();

        $r = $this->chat('cancelar');

        $this->assertStringContainsString('Proceso detenido', $r['respuesta']);
        $this->assertFalse($r['en_proceso']);
        $this->assertSame(0, Cita::count());
    }

    public function test_una_respuesta_no_reconocida_en_el_resumen_repite_las_opciones(): void
    {
        $this->llegarAlResumen();

        $r = $this->chat('quizás');

        $this->assertStringContainsString('Pulsa "Confirmar"', $r['respuesta']);
        $this->assertContains('confirmar', $this->valores($r));
        $this->assertSame(0, Cita::count());
    }

    public function test_si_otro_toma_el_turno_antes_de_confirmar_ofrece_alternativas(): void
    {
        $this->llegarAlResumen();
        Cita::create(['paciente_id' => 1, 'medico_id' => 1, 'fecha' => '2026-11-17', 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'Pendiente']);

        $r = $this->chat('confirmar');
        $this->assertStringContainsString('No se pudo guardar', $r['respuesta']);
        $this->assertSame('17/11/2026 · 11:30 AM', $this->textos($r)[0]);

        $this->chat('1');
        $r = $this->chat('confirmar');
        $this->assertStringContainsString('agendada exitosamente', $r['respuesta']);
        $this->assertSame(2, Cita::count());
    }

    public function test_el_paciente_con_sesion_no_puede_cambiar_el_dni(): void
    {
        $this->actingAs(Usuario::find(4));
        $this->chat('quiero una cita');

        $r = $this->chat('cambiar dni');

        $this->assertStringContainsString('Estás identificado con tu sesión', $r['respuesta']);
        $this->assertSame(['Cardiología', 'Pediatría'], $this->valores($r));
    }
}
