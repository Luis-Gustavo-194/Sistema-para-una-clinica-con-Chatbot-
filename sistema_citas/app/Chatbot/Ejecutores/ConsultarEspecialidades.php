<?php

namespace App\Chatbot\Ejecutores;

use App\Chatbot\Respuesta;
use App\Models\Especialidad;

class ConsultarEspecialidades extends EjecutorSimple
{
    protected function responder(): Respuesta
    {
        $nombres = Especialidad::where('estado', 1)->orderBy('nombre')->pluck('nombre');

        if ($nombres->isEmpty()) {
            return new Respuesta('Por el momento no hay especialidades registradas.');
        }

        return new Respuesta('Contamos con especialidades en '.$nombres->join(', ', ' y ').'. Si deseas, puedo agendarte una cita.');
    }
}
