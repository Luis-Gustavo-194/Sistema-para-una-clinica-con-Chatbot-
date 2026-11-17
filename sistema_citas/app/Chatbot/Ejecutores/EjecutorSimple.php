<?php

namespace App\Chatbot\Ejecutores;

use App\Chatbot\Conversacion;
use App\Chatbot\Entidades;
use App\Chatbot\Respuesta;

// Intenciones que se responden en un solo mensaje (no abren un flujo)
abstract class EjecutorSimple implements Ejecutor
{
    abstract protected function responder(): Respuesta;

    public function iniciar(Conversacion $conversacion, Entidades $entidades): Respuesta
    {
        return $this->responder();
    }

    public function continuar(Conversacion $conversacion, string $mensaje, Entidades $entidades): Respuesta
    {
        return $this->responder();
    }

    public function corregir(Conversacion $conversacion, ?string $campo): Respuesta
    {
        return $this->responder();
    }
}
