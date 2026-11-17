<?php

namespace Tests\Feature\Chatbot;

use App\Models\Cita;
use Tests\Concerns\ConversaConChatbot;
use Tests\TestCase;

// HU09 y HU02: el chatbot aprovecha la especialidad, el médico, la fecha, la hora y el DNI
// que el paciente escribe, y si el DNI no existe le ofrece registrarse.
class EntidadesTest extends TestCase
{
    use ConversaConChatbot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararChatbot();
    }

    public function test_una_frase_completa_llega_directo_al_resumen_y_confirmar_crea_la_cita_pendiente(): void
    {
        $r = $this->chat('Quiero cita de cardiología con la Dra. Ríos mañana a las 10, mi DNI es 11111111');

        $this->assertStringContainsString('Revisa los datos de tu cita', $r['respuesta']);
        $this->assertStringContainsString('Cardiología', $r['respuesta']);
        $this->assertStringContainsString('Ana Ríos', $r['respuesta']);
        $this->assertStringContainsString('Martes 17/11/2026', $r['respuesta']);
        $this->assertStringContainsString('10:00 AM', $r['respuesta']);
        $this->assertStringContainsString('Consultorio: 101', $r['respuesta']);
        $this->assertSame(['confirmar', 'cambiar horario', 'cambiar medico', 'cancelar'], $this->valores($r));
        $this->assertTrue($r['en_proceso']);
        $this->assertSame(0, Cita::count());

        $r = $this->chat('Confirmar');

        $cita = Cita::sole();
        $this->assertSame(['Pendiente', 'chatbot', '2026-11-17', '10:00:00'], [$cita->estado, $cita->canal, $cita->fecha, $cita->hora_inicio]);
        $this->assertStringContainsString("Tu cita N.° {$cita->id} ha sido agendada exitosamente", $r['respuesta']);
        $this->assertStringContainsString('Estado: Pendiente', $r['respuesta']);
        $this->assertFalse($r['en_proceso']);
    }

    public function test_el_dni_en_el_primer_mensaje_se_salta_ese_paso(): void
    {
        $r = $this->chat('quiero una cita, mi dni es 11111111');

        $this->assertStringContainsString('¡Hola Juan! Te encontré en el sistema', $r['respuesta']);
        $this->assertSame(['Cardiología', 'Pediatría'], $this->valores($r));
    }

    public function test_dni_inexistente_ofrece_el_registro_y_deja_reintentar(): void
    {
        $this->chat('quiero una cita');
        $r = $this->chat('99999999');

        $this->assertStringContainsString('No encontré ningún paciente registrado con el DNI 99999999', $r['respuesta']);
        $this->assertSame(['texto' => 'Crear mi cuenta', 'url' => route('registro')], $r['enlace']);
        $this->assertTrue($r['en_proceso']);

        $r = $this->chat('123');
        $this->assertStringContainsString('no parece un DNI válido', $r['respuesta']);

        $r = $this->chat('11111111');
        $this->assertStringContainsString('¡Hola Juan!', $r['respuesta']);
    }

    public function test_reconoce_la_especialidad_sin_tildes_o_abreviada(): void
    {
        $this->chat('quiero una cita, mi dni es 11111111');
        $r = $this->chat('cardio');
        $this->assertSame(['1'], $this->valores($r));

        $this->chat('atrás');
        $r = $this->chat('necesito un pediatra');
        $this->assertSame(['2'], $this->valores($r));
    }

    public function test_el_medico_se_puede_escribir_por_su_apellido(): void
    {
        $this->chat('cita de cardiologia, dni 11111111');
        $r = $this->chat('con la doctora rios');

        $this->assertStringContainsString('Has seleccionado al Dr. Ríos', $r['respuesta']);
        $this->assertSame(['10:00 AM', '11:30 AM'], $this->textos($r));
    }

    public function test_un_apellido_comun_sin_titulo_no_se_toma_como_medico(): void
    {
        // "paz" es el apellido del pediatra, pero sin "Dr." no se asume que lo pidió
        $r = $this->chat('quiero paz y una cita de pediatría, dni 11111111');

        $this->assertStringContainsString('Para Pediatría tenemos a los siguientes médicos', $r['respuesta']);
        $this->assertSame(['2'], $this->valores($r));
    }

    public function test_con_fecha_sin_hora_ofrece_los_turnos_de_ese_dia(): void
    {
        $r = $this->chat('cita con la dra ríos el viernes, dni 11111111');

        $this->assertStringContainsString('Para el 20/11/2026 tiene disponibles estos horarios', $r['respuesta']);
        $this->assertSame(['10:00 AM', '11:30 AM'], $this->textos($r));
    }

    public function test_hora_ocupada_ofrece_los_horarios_libres_mas_cercanos(): void
    {
        Cita::create(['paciente_id' => 1, 'medico_id' => 1, 'fecha' => '2026-11-17', 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'Pendiente']);

        $r = $this->chat('cita con la dra ríos mañana a las 10, dni 11111111');

        $this->assertStringContainsString('El 17/11/2026 a las 10:00 AM no está disponible', $r['respuesta']);
        $this->assertSame(['17/11/2026 · 11:30 AM', '18/11/2026 · 10:00 AM', '18/11/2026 · 11:30 AM'], $this->textos($r));
    }

    public function test_hoy_o_fuera_de_las_dos_semanas_avisa_y_ofrece_el_primer_dia_libre(): void
    {
        $r = $this->chat('cita con la dra ríos para hoy, dni 11111111');
        $this->assertStringContainsString('se reservan desde mañana', $r['respuesta']);
        $this->assertStringContainsString('Para el 17/11/2026', $r['respuesta']);

        $r = $this->chat('el 15 de enero');
        $this->assertStringContainsString('Solo puedo reservar hasta el 30/11/2026', $r['respuesta']);
    }
}
