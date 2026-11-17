<?php

namespace App\Chatbot;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

// HU09: convierte fechas escritas por el paciente ("mañana", "el viernes", "15 de diciembre", "20/11")
// en formato ISO (Y-m-d). Recibe el texto ya normalizado (NormalizadorTexto) y la fecha de hoy.
class ResolutorFechas
{
    const MESES = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7,
        'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
    ];

    // Número ISO del día (1 = lunes)
    const DIAS = ['lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sabado' => 6, 'domingo' => 7];

    const NUMEROS = ['un' => 1, 'uno' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10];

    public static function resolver(string $texto, CarbonInterface $hoy): ?string
    {
        $hoy = CarbonImmutable::instance($hoy)->startOfDay();

        // 20/11, 20-11 o 20/11/2026
        if (preg_match('/\b(\d{1,2})[\/-](\d{1,2})(?:[\/-](\d{2,4}))?\b/', $texto, $m)) {
            return self::fecha((int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : null, $hoy);
        }

        // 15 de diciembre (de 2026)
        $meses = implode('|', array_keys(self::MESES));
        if (preg_match("/\b(\d{1,2}) (?:de )?({$meses})(?: (?:de |del )?(\d{4}))?\b/", $texto, $m)) {
            return self::fecha((int) $m[1], self::MESES[$m[2]], isset($m[3]) ? (int) $m[3] : null, $hoy);
        }

        if (preg_match('/\bpasado manana\b/', $texto)) {
            return $hoy->addDays(2)->toDateString();
        }

        // "mañana" es fecha salvo que sea la franja horaria: "en/por/de la mañana", "esta mañana"
        if (preg_match('/(?<!la )(?<!esta )\bmanana\b/', $texto)) {
            return $hoy->addDay()->toDateString();
        }

        if (preg_match('/\bhoy\b/', $texto)) {
            return $hoy->toDateString();
        }

        $numeros = implode('|', array_keys(self::NUMEROS));
        if (preg_match("/\b(?:en|dentro de) (\d{1,2}|{$numeros}) dias?\b/", $texto, $m)) {
            return $hoy->addDays(self::NUMEROS[$m[1]] ?? (int) $m[1])->toDateString();
        }

        if (preg_match('/\b(?:la )?(?:proxima|siguiente) semana\b|\bsemana que viene\b/', $texto)) {
            return $hoy->addDays((8 - $hoy->dayOfWeekIso) % 7 ?: 7)->toDateString();
        }

        // "el viernes", "el próximo lunes": la siguiente vez que llega ese día (nunca hoy)
        $dias = implode('|', array_keys(self::DIAS));
        if (preg_match("/\b({$dias})\b/", $texto, $m)) {
            return $hoy->addDays((self::DIAS[$m[1]] - $hoy->dayOfWeekIso + 7) % 7 ?: 7)->toDateString();
        }

        // "el 20" o "el día 20": ese día de este mes, o del siguiente si ya pasó
        if (preg_match('/\bel (?:dia )?(\d{1,2})\b(?! ?(?:de la|:|am|pm|hrs?|horas?))/', $texto, $m)) {
            $dia = (int) $m[1];
            $candidata = checkdate($hoy->month, $dia, $hoy->year) ? $hoy->setDate($hoy->year, $hoy->month, $dia) : null;
            if ($candidata && $candidata->gte($hoy)) {
                return $candidata->toDateString();
            }
            $siguiente = $hoy->startOfMonth()->addMonth();

            return checkdate($siguiente->month, $dia, $siguiente->year) ? $siguiente->setDate($siguiente->year, $siguiente->month, $dia)->toDateString() : null;
        }

        return null;
    }

    // Sin año explícito, una fecha que ya pasó se entiende del año siguiente
    private static function fecha(int $dia, int $mes, ?int $anio, CarbonImmutable $hoy): ?string
    {
        if ($anio !== null && $anio < 100) {
            $anio += 2000;
        }
        $anioUsado = $anio ?? $hoy->year;

        if (! checkdate($mes, $dia, $anioUsado)) {
            return null;
        }

        $fecha = CarbonImmutable::create($anioUsado, $mes, $dia)->startOfDay();
        if ($anio === null && $fecha->lt($hoy)) {
            $fecha = $fecha->addYear();
            if (! checkdate($mes, $dia, $fecha->year)) {
                return null;
            }
        }

        return $fecha->toDateString();
    }
}
