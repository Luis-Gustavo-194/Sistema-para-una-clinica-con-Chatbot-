<?php

namespace App\Chatbot\Ejecutores;

use App\Chatbot\Conversacion;
use App\Chatbot\Entidades;
use App\Chatbot\Respuesta;

// Atiende una intención del chatbot usando los servicios de la aplicación directamente
// (DisponibilidadService, CitaService), sin pasar por ningún endpoint HTTP.
interface Ejecutor
{
    // Primer mensaje de la intención; las entidades permiten saltarse preguntas ya respondidas
    public function iniciar(Conversacion $conversacion, Entidades $entidades): Respuesta;

    // Respuesta del paciente a la pregunta pendiente ($conversacion->paso())
    public function continuar(Conversacion $conversacion, string $mensaje, Entidades $entidades): Respuesta;

    // "Atrás" ($campo null) o "cambiar especialidad|medico|fecha|horario|dni"
    public function corregir(Conversacion $conversacion, ?string $campo): Respuesta;
}
