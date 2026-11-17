<?php

namespace App\Chatbot\Ejecutores;

use App\Chatbot\Conversacion;
use App\Chatbot\Respuesta;
use App\Models\Cita;

// Elige la cita a reprogramar y continúa con el mismo flujo de agendar (que la actualiza en vez de crear otra)
class ReprogramarCita implements Ejecutor
{
    use EligeCitaVigente;

    const FLUJO = 'reprogramar_cita';

    public function __construct(private AgendarCita $agendar) {}

    protected function accion(): string
    {
        return 'reprogramar';
    }

    protected function pedirDni(): string
    {
        return 'Entiendo que deseas reprogramar un turno. Por favor, escribe tu número de DNI para buscar tus citas vigentes.';
    }

    protected function citaElegida(Conversacion $conversacion, Cita $cita): Respuesta
    {
        $conversacion->cambiarFlujo(AgendarCita::FLUJO);
        $conversacion->olvidar('citas_ofrecidas', 'opciones');
        $conversacion->guardar(['cita_a_reprogramar' => $cita->id]);

        return $this->agendar->avanzar($conversacion, 'Excelente, vamos a reagendar tu turno.');
    }
}
