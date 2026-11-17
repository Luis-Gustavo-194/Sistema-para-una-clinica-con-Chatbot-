<?php

namespace App\Chatbot\Ejecutores;

use App\Chatbot\Respuesta;

class Saludo extends EjecutorSimple
{
    protected function responder(): Respuesta
    {
        return new Respuesta('¡Hola! Soy el asistente virtual de la clínica. Puedes pedirme agendar, cancelar o reprogramar una cita.');
    }
}
