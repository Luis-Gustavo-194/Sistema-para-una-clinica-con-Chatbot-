<?php

namespace Tests\Feature\Chatbot;

use Tests\Concerns\ConversaConChatbot;
use Tests\TestCase;

// POST /chatbot/enviar: validación, formato de la respuesta y límite de mensajes
class EndpointTest extends TestCase
{
    use ConversaConChatbot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararChatbot();
    }

    public function test_la_ruta_tiene_nombre_y_valida_el_mensaje(): void
    {
        $this->assertSame(url('/chatbot/enviar'), route('chatbot.enviar'));

        $this->postJson('/chatbot/enviar', [])->assertStatus(422)->assertJsonValidationErrors('mensaje');
        $this->postJson('/chatbot/enviar', ['mensaje' => str_repeat('a', 501)])->assertStatus(422);
    }

    public function test_formato_de_la_respuesta(): void
    {
        $this->intencion = 'saludo';

        $r = $this->chat('hola');

        // Sin botones ni enlace, esas claves no se envían
        $this->assertSame(['respuesta', 'en_proceso'], array_keys($r));
        $this->assertFalse($r['en_proceso']);
    }

    public function test_limita_los_mensajes_por_conversacion(): void
    {
        $this->intencion = 'saludo';

        for ($i = 0; $i < 30; $i++) {
            $this->chat('hola');
        }

        $this->chat('hola', estado: 429);

        // Otra conversación (otra sesión) no queda bloqueada
        $this->chat('hola', 'B');
    }
}
