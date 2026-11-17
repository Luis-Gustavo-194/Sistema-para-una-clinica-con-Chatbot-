<?php

namespace App\Chatbot\Ejecutores;

use App\Chatbot\Conversacion;
use App\Chatbot\Respuesta;
use App\Models\Cita;
use App\Services\CitaService;

// Cancela una cita vigente del paciente; el registro queda como 'Cancelada' y el turno se libera
class CancelarCita implements Ejecutor
{
    use EligeCitaVigente;

    const FLUJO = 'cancelar_cita';

    protected function accion(): string
    {
        return 'cancelar';
    }

    protected function pedirDni(): string
    {
        return 'Entiendo que deseas cancelar tu cita. Por favor, escribe tu número de DNI.';
    }

    protected function citaElegida(Conversacion $conversacion, Cita $cita): Respuesta
    {
        $conversacion->terminar();

        try {
            CitaService::cancelar($cita);
        } catch (\DomainException $e) {
            return new Respuesta("⚠️ {$e->getMessage()}");
        }

        $this->auditar("Canceló la cita #{$cita->id} por el chatbot", $cita);

        return new Respuesta('✅ Tu cita ha sido cancelada exitosamente. El horario quedó libre. ¿Te ayudo con algo más?');
    }
}
