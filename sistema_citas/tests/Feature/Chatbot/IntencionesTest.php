<?php

namespace Tests\Feature\Chatbot;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConversaConChatbot;
use Tests\TestCase;

// Servicio de intenciones: con confianza alta ejecuta, con confianza intermedia pregunta
// "¿Quisiste decir…?" y con confianza baja ofrece el menú. Los umbrales están en config/chatbot.php.
class IntencionesTest extends TestCase
{
    use ConversaConChatbot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararChatbot();
    }

    public function test_con_confianza_alta_ejecuta_la_intencion(): void
    {
        $this->confianza = 0.8;

        $r = $this->chat('quiero sacar una cita');

        $this->assertStringContainsString('escribe tu número de DNI', $r['respuesta']);
        $this->assertTrue($r['en_proceso']);
    }

    public function test_con_confianza_intermedia_pregunta_y_el_boton_no_vuelve_a_consultar_a_python(): void
    {
        $this->intencion = 'cancelar_cita';
        $this->confianza = -0.2;
        $this->candidatas = [
            ['intencion' => 'cancelar_cita', 'puntaje' => -0.2],
            ['intencion' => 'reprogramar_cita', 'puntaje' => -0.4],
            ['intencion' => 'agendar_cita', 'puntaje' => -0.9],
        ];

        $r = $this->chat('mi cita del viernes');

        $this->assertStringContainsString('¿Quisiste decir', $r['respuesta']);
        $this->assertSame(['cancelar_cita', 'reprogramar_cita'], $this->valores($r));
        $this->assertSame(['Cancelar una cita', 'Reprogramar una cita'], $this->textos($r));
        $this->assertFalse($r['en_proceso']);

        $r = $this->chat('reprogramar_cita');

        $this->assertStringContainsString('deseas reprogramar un turno', $r['respuesta']);
        Http::assertSentCount(1);
    }

    public function test_con_confianza_baja_no_entiende_y_ofrece_el_menu(): void
    {
        $this->confianza = -0.9;

        $r = $this->chat('precio del dólar');

        $this->assertStringContainsString('No logré entender', $r['respuesta']);
        $this->assertSame(config('chatbot.menu'), $this->valores($r));
    }

    public function test_los_umbrales_se_configuran(): void
    {
        config(['chatbot.umbral_confianza' => 0.5]);
        $this->confianza = 0.3;

        $r = $this->chat('quiero sacar una cita');

        $this->assertStringContainsString('¿Quisiste decir', $r['respuesta']);
        $this->assertSame(['agendar_cita'], $this->valores($r));
    }

    public function test_con_python_apagado_el_menu_sigue_funcionando(): void
    {
        Http::fake(['127.0.0.1:8001/*' => fn () => throw new ConnectionException('apagado')]);

        $r = $this->chat('hola');
        $this->assertStringContainsString('apagado', $r['respuesta']);
        $this->assertSame(config('chatbot.menu'), $this->valores($r));

        // El botón del menú abre el flujo sin necesitar a Python
        $r = $this->chat('agendar_cita');
        $this->assertStringContainsString('escribe tu número de DNI', $r['respuesta']);
    }

    public function test_si_python_responde_con_error_se_avisa(): void
    {
        $this->estadoNlp = 500;

        $r = $this->chat('hola');

        $this->assertStringContainsString('tuvo un problema', $r['respuesta']);
    }

    public function test_la_url_del_motor_nlp_se_configura(): void
    {
        config(['chatbot.nlp_url' => 'http://chatbot.railway.internal:8001/']);
        Http::fake(['chatbot.railway.internal:8001/predecir' => Http::response(['intencion' => 'saludo', 'confianza' => 1.0])]);

        $r = $this->chat('hola');

        $this->assertStringContainsString('asistente virtual', $r['respuesta']);
    }
}
