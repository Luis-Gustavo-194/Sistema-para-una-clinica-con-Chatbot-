<?php

namespace App\Support;

// HU18: oculta parte de los datos personales antes de guardarlos en la bitácora
class DatosSensibles
{
    public static function enmascarar(string $texto): string
    {
        // Correos: m***@dominio.com
        $texto = preg_replace_callback(
            '/\b([\w.+-])[\w.+-]*@([\w-]+(?:\.[\w-]+)+)\b/u',
            fn ($m) => "{$m[1]}***@{$m[2]}",
            $texto
        );

        // Teléfonos móviles de 9 dígitos: 9*****777
        $texto = preg_replace_callback(
            '/(?<!\d)9\d{8}(?!\d)/',
            fn ($m) => '9*****'.substr($m[0], -3),
            $texto
        );

        // DNI de 8 dígitos: 70****01
        return preg_replace_callback(
            '/(?<!\d)\d{8}(?!\d)/',
            fn ($m) => substr($m[0], 0, 2).'****'.substr($m[0], -2),
            $texto
        );
    }
}
