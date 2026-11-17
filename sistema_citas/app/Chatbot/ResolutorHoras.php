<?php

namespace App\Chatbot;

// HU09: convierte horas escritas por el paciente ("a las 10", "3 pm", "10 y media", "11 de la mañana")
// en formato H:i. Recibe el texto ya normalizado. Un número suelto ("1") no es una hora,
// para no confundirlo con el valor de los botones de opciones.
class ResolutorHoras
{
    const NUMEROS = [
        'una' => 1, 'uno' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5, 'seis' => 6,
        'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10, 'once' => 11, 'doce' => 12,
    ];

    public static function resolver(string $texto): ?string
    {
        if (preg_match('/\bmediodia\b/', $texto)) {
            return '12:00';
        }

        $franja = preg_match('/\b(?:de|en|por) la (manana|tarde|noche)\b/', $texto, $f) ? $f[1] : null;
        $numeros = implode('|', array_keys(self::NUMEROS));

        // 10:30, 10:30 am
        if (preg_match('/\b(\d{1,2}):(\d{2})\s*(am|pm|a m|p m)?\b/', $texto, $m)) {
            return self::formatear((int) $m[1], (int) $m[2], $m[3] ?? null, $franja);
        }

        // 3 pm, 10am, 3 p m
        if (preg_match('/\b(\d{1,2})\s*(am|pm|a m|p m)\b/', $texto, $m)) {
            return self::formatear((int) $m[1], 0, $m[2], $franja);
        }

        // a las 10, a la una, a las 4 y media
        if (preg_match("/\b(?:a )?las? (\d{1,2}|{$numeros})(?: y (media|cuarto))?\b/", $texto, $m)) {
            return self::formatear(self::NUMEROS[$m[1]] ?? (int) $m[1], self::minutos($m[2] ?? null), null, $franja);
        }

        // 11 de la mañana, 4 y media de la tarde
        if (preg_match("/\b(\d{1,2}|{$numeros})(?: y (media|cuarto))? (?:de|en|por) la (?:manana|tarde|noche)\b/", $texto, $m)) {
            return self::formatear(self::NUMEROS[$m[1]] ?? (int) $m[1], self::minutos($m[2] ?? null), null, $franja);
        }

        return null;
    }

    private static function minutos(?string $fraccion): int
    {
        return match ($fraccion) {
            'media' => 30,
            'cuarto' => 15,
            default => 0,
        };
    }

    private static function formatear(int $hora, int $minuto, ?string $meridiano, ?string $franja): ?string
    {
        $meridiano = $meridiano ? str_replace(' ', '', $meridiano) : null;

        if ($meridiano === 'pm' && $hora < 12) {
            $hora += 12;
        } elseif ($meridiano === 'am' && $hora === 12) {
            $hora = 0;
        } elseif (! $meridiano && in_array($franja, ['tarde', 'noche'], true) && $hora < 12) {
            $hora += 12;
        } elseif (! $meridiano && ! $franja && $hora >= 1 && $hora <= 6) {
            // Sin "am/pm" ni franja, de 1 a 6 se entiende de la tarde (la clínica no atiende de madrugada)
            $hora += 12;
        }

        if ($hora > 23 || $minuto > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hora, $minuto);
    }
}
