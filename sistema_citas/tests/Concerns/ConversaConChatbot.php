<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

// Datos y ayudantes para las pruebas del chatbot. "Hoy" es el lunes 16/11/2026 a las 8:00.
// El motor NLP de Python se simula: responde $intencion con $confianza y $candidatas.
trait ConversaConChatbot
{
    use EsquemaClinica;

    protected string $intencion = 'agendar_cita';

    protected ?float $confianza = 1.0;

    protected array $candidatas = [];

    protected int $estadoNlp = 200;

    private array $cookiesChat = [];

    protected function prepararChatbot(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(8, 0));
        $this->crearEsquemaClinica();

        DB::table('usuarios')->insert([
            ['id' => 2, 'rol_id' => 2, 'correo' => 'rios@x.com', 'contrasena' => Hash::make('x'), 'estado' => 1],
            ['id' => 4, 'rol_id' => 1, 'correo' => 'juan@x.com', 'contrasena' => Hash::make('x'), 'estado' => 1],
            ['id' => 6, 'rol_id' => 2, 'correo' => 'paz@x.com', 'contrasena' => Hash::make('x'), 'estado' => 1],
        ]);
        DB::table('especialidades')->insert([['id' => 1, 'nombre' => 'Cardiología'], ['id' => 2, 'nombre' => 'Pediatría']]);
        DB::table('medicos')->insert([
            ['id' => 1, 'usuario_id' => 2, 'especialidad_id' => 1, 'colegiatura' => 'CMP-1', 'nombres' => 'Ana', 'apellidos' => 'Ríos'],
            ['id' => 2, 'usuario_id' => 6, 'especialidad_id' => 2, 'colegiatura' => 'CMP-2', 'nombres' => 'Luis', 'apellidos' => 'Paz'],
        ]);
        DB::table('pacientes')->insert([
            ['id' => 1, 'usuario_id' => 4, 'dni' => '11111111', 'nombres' => 'Juan', 'apellidos' => 'Pérez'],
        ]);

        // Ambos médicos atienden todos los días a las 10:00 y a las 11:30 (30 min), en el consultorio 101
        foreach ([1, 2] as $medicoId) {
            for ($dia = 1; $dia <= 7; $dia++) {
                DB::table('horarios_medicos')->insert([
                    ['medico_id' => $medicoId, 'dia_semana' => $dia, 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'duracion_consulta_minutos' => 30, 'consultorio' => '101'],
                    ['medico_id' => $medicoId, 'dia_semana' => $dia, 'hora_inicio' => '11:30:00', 'hora_fin' => '12:00:00', 'duracion_consulta_minutos' => 30, 'consultorio' => '101'],
                ]);
            }
        }

        Http::fake(['127.0.0.1:8001/*' => fn () => Http::response(array_filter([
            'intencion' => $this->intencion,
            'confianza' => $this->confianza,
            'candidatas' => $this->candidatas ?: null,
        ], fn ($v) => $v !== null), $this->estadoNlp)]);
    }

    // Envía un mensaje como un navegador con su propia cookie de sesión y devuelve el JSON completo
    protected function chat(string $mensaje, string $navegador = 'A', int $estado = 200): array
    {
        app('session')->driver()->flush();
        $this->defaultCookies = [];

        $peticion = $this->withCredentials();
        if (isset($this->cookiesChat[$navegador])) {
            $peticion = $peticion->withCookie(config('session.cookie'), $this->cookiesChat[$navegador]);
        }

        $respuesta = $peticion->postJson('/chatbot/enviar', ['mensaje' => $mensaje])->assertStatus($estado);
        if ($cookie = $respuesta->getCookie(config('session.cookie'))) {
            $this->cookiesChat[$navegador] = $cookie->getValue();
        }

        return $respuesta->json() ?? [];
    }

    protected function valores(array $respuesta): array
    {
        return array_column($respuesta['opciones'] ?? [], 'valor');
    }

    protected function textos(array $respuesta): array
    {
        return array_column($respuesta['opciones'] ?? [], 'texto');
    }
}
