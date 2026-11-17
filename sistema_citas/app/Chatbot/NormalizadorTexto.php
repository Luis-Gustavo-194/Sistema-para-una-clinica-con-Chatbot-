<?php

namespace App\Chatbot;

use Illuminate\Support\Str;

// HU09: deja el mensaje en minúsculas, sin tildes ni signos y con un solo espacio entre palabras,
// para que "¡Mañana a las 10:30, Cardiología!" y "manana a las 10:30 cardiologia" se lean igual.
// Se conservan ":", "/" y "-" porque forman horas y fechas.
class NormalizadorTexto
{
    public static function normalizar(string $texto): string
    {
        $texto = Str::lower(Str::ascii($texto));
        $texto = preg_replace('/[^a-z0-9:\/\-\s]/', ' ', $texto);

        return Str::squish($texto);
    }
}
