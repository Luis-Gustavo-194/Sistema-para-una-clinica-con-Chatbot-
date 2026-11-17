<?php

namespace App\Chatbot;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

// Pregunta al motor NLP de Python (FastAPI) qué quiere el paciente y decide según la confianza:
// alta → se acepta; intermedia → se le pregunta "¿Quisiste decir…?"; baja → no se entendió.
// Los umbrales están en config/chatbot.php.
class ServicioIntenciones
{
    public function interpretar(string $mensaje): Interpretacion
    {
        try {
            $respuesta = Http::timeout(config('chatbot.timeout'))
                ->post(rtrim(config('chatbot.nlp_url'), '/').'/predecir', ['texto' => $mensaje]);
        } catch (ConnectionException) {
            throw new MotorNlpNoDisponible('El motor NLP de Python está apagado.');
        }

        if (! $respuesta->successful()) {
            throw new MotorNlpNoDisponible('El motor NLP tuvo un problema. Intenta de nuevo en unos momentos.');
        }

        $intencion = (string) $respuesta->json('intencion', 'desconocida');
        $confianza = $respuesta->json('confianza');
        $confianza = is_numeric($confianza) ? (float) $confianza : null;

        // Candidatas conocidas, de mayor a menor puntaje (Python envía las 3 mejores)
        $conocidas = config('chatbot.intenciones');
        $candidatas = collect($respuesta->json('candidatas') ?? [['intencion' => $intencion]])
            ->pluck('intencion')
            ->filter(fn ($i) => is_string($i) && array_key_exists($i, $conocidas))
            ->values()
            ->all();

        return new Interpretacion($intencion, $confianza, $candidatas, $this->decidir($intencion, $confianza));
    }

    private function decidir(string $intencion, ?float $confianza): string
    {
        if (! array_key_exists($intencion, config('chatbot.intenciones'))) {
            return Interpretacion::DESCONOCIDA;
        }

        // Sin puntaje (versión anterior del motor) se confía en la intención recibida
        if ($confianza === null || $confianza >= config('chatbot.umbral_confianza')) {
            return Interpretacion::ACEPTADA;
        }

        return $confianza >= config('chatbot.umbral_aclaracion') ? Interpretacion::ACLARAR : Interpretacion::DESCONOCIDA;
    }
}
