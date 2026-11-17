<?php

namespace App\Http\Controllers;

use App\Chatbot\Conversacion;
use App\Chatbot\Ejecutores\AgendarCita;
use App\Chatbot\Ejecutores\CancelarCita;
use App\Chatbot\Ejecutores\ConsultarAgendaMedico;
use App\Chatbot\Ejecutores\ConsultarEspecialidades;
use App\Chatbot\Ejecutores\Ejecutor;
use App\Chatbot\Ejecutores\ReprogramarCita;
use App\Chatbot\Ejecutores\Saludo;
use App\Chatbot\Entidades;
use App\Chatbot\ExtractorEntidades;
use App\Chatbot\Interpretacion;
use App\Chatbot\MotorNlpNoDisponible;
use App\Chatbot\NormalizadorTexto;
use App\Chatbot\Respuesta;
use App\Chatbot\ServicioIntenciones;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

// POST /chatbot/enviar: recibe un mensaje y devuelve {respuesta, opciones?, enlace?, en_proceso}.
// Si hay un flujo en curso, el mensaje responde a su pregunta pendiente; si no, el motor NLP
// decide la intención y el ejecutor correspondiente la atiende.
class ChatbotController extends Controller
{
    const EJECUTORES = [
        'saludo' => Saludo::class,
        'agendar_cita' => AgendarCita::class,
        'cancelar_cita' => CancelarCita::class,
        'reprogramar_cita' => ReprogramarCita::class,
        'consultar_especialidades' => ConsultarEspecialidades::class,
        'consultar_agenda_medico' => ConsultarAgendaMedico::class,
    ];

    // Dentro de un flujo (texto normalizado)
    const PALABRAS_CANCELAR = ['cancelar', 'cancelar proceso', 'cancelar todo', 'salir', 'detener', 'ya no', 'inicio'];

    const PALABRAS_ATRAS = ['atras', 'volver', 'regresar', 'paso anterior'];

    // "cambiar médico", "corregir la fecha", "otro horario"... → campo que se corrige
    const CAMPOS = [
        'especialidad' => 'especialidad', 'medico' => 'medico', 'doctor' => 'medico', 'doctora' => 'medico',
        'fecha' => 'fecha', 'dia' => 'fecha', 'horario' => 'horario', 'hora' => 'horario', 'dni' => 'dni',
    ];

    public function __construct(
        private Container $contenedor,
        private ServicioIntenciones $intenciones,
        private ExtractorEntidades $extractor,
    ) {}

    public function procesarMensaje(Request $request)
    {
        $request->validate(['mensaje' => 'required|string|max:500']);
        $conversacion = new Conversacion($request->session());

        $respuesta = $this->responder($conversacion, trim($request->mensaje));

        return response()->json($respuesta->toArray() + ['en_proceso' => $conversacion->enProceso()]);
    }

    private function responder(Conversacion $conversacion, string $mensaje): Respuesta
    {
        if ($flujo = $conversacion->flujo()) {
            return $this->continuarFlujo($conversacion, $this->ejecutor($flujo), $mensaje);
        }

        // Botón de una aclaración o del menú: se atiende sin volver a consultar a Python
        $ofrecidas = $conversacion->intencionesOfrecidas();
        $conversacion->olvidarIntencionesOfrecidas();
        if (in_array($mensaje, $ofrecidas, true)) {
            return $this->ejecutor($mensaje)->iniciar($conversacion, new Entidades);
        }

        try {
            $interpretacion = $this->intenciones->interpretar($mensaje);
        } catch (MotorNlpNoDisponible $e) {
            return $this->menu($conversacion, $e->getMessage().' Mientras tanto, puedes elegir una opción:');
        }

        return match ($interpretacion->decision) {
            Interpretacion::ACEPTADA => $this->ejecutor($interpretacion->intencion)->iniciar($conversacion, $this->extractor->extraer($mensaje)),
            Interpretacion::ACLARAR => $this->aclarar($conversacion, $interpretacion),
            default => $this->menu($conversacion, 'No logré entender tu solicitud. ¿Podrías escribirlo de otra forma? También puedes elegir una opción:'),
        };
    }

    // Antes de pasar el mensaje al ejecutor se atienden "cancelar", "atrás" y "cambiar X"
    private function continuarFlujo(Conversacion $conversacion, Ejecutor $ejecutor, string $mensaje): Respuesta
    {
        $texto = NormalizadorTexto::normalizar($mensaje);

        if (in_array($texto, self::PALABRAS_CANCELAR, true)) {
            $conversacion->terminar();

            return new Respuesta('🚫 Proceso detenido. He cancelado la operación actual. ¿En qué más te puedo ayudar?');
        }

        if (in_array($texto, self::PALABRAS_ATRAS, true)) {
            return $ejecutor->corregir($conversacion, null);
        }

        $campos = implode('|', array_keys(self::CAMPOS));
        if (preg_match("/^(?:quiero )?(?:cambiar|cambia|corregir|corrige|otro|otra)(?: de| el| la| mi)? ({$campos})$/", $texto, $m)) {
            return $ejecutor->corregir($conversacion, self::CAMPOS[$m[1]]);
        }

        return $ejecutor->continuar($conversacion, $mensaje, $this->extractor->extraer($mensaje));
    }

    // Confianza intermedia: "¿Quisiste decir…?" con las intenciones más probables como botones
    private function aclarar(Conversacion $conversacion, Interpretacion $interpretacion): Respuesta
    {
        $candidatas = array_slice($interpretacion->candidatas ?: [$interpretacion->intencion], 0, 2);

        return $this->ofrecer($conversacion, 'No estoy seguro de haberte entendido. ¿Quisiste decir alguna de estas opciones?', $candidatas);
    }

    private function menu(Conversacion $conversacion, string $texto): Respuesta
    {
        return $this->ofrecer($conversacion, $texto, config('chatbot.menu'));
    }

    private function ofrecer(Conversacion $conversacion, string $texto, array $intenciones): Respuesta
    {
        $conversacion->ofrecerIntenciones($intenciones);
        $etiquetas = config('chatbot.intenciones');

        return new Respuesta($texto, array_map(fn ($i) => ['texto' => $etiquetas[$i], 'valor' => $i], $intenciones));
    }

    private function ejecutor(string $intencion): Ejecutor
    {
        return $this->contenedor->make(self::EJECUTORES[$intencion]);
    }
}
