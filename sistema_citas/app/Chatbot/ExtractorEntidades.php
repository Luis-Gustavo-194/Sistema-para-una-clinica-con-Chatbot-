<?php

namespace App\Chatbot;

use App\Models\Especialidad;
use App\Models\Medico;

// HU09: reconoce en el mensaje la especialidad, el médico, la fecha, la hora y el DNI.
// Solo considera especialidades y médicos activos.
class ExtractorEntidades
{
    // Letras iniciales en común para aceptar una palabra como especialidad ("cardiólogo" → Cardiología)
    const PREFIJO_ESPECIALIDAD = 6;

    public function extraer(string $mensaje): Entidades
    {
        $texto = NormalizadorTexto::normalizar($mensaje);

        return new Entidades(
            dni: $this->dni($mensaje),
            especialidad: $this->especialidad($texto),
            medico: $this->medico($texto),
            fecha: ResolutorFechas::resolver($texto, today()),
            hora: ResolutorHoras::resolver($texto),
        );
    }

    // 8 dígitos seguidos ("mi DNI es 70123456") o el mensaje entero con separadores ("70.123.456")
    public function dni(string $mensaje): ?string
    {
        if (preg_match('/(?<!\d)\d{8}(?!\d)/', $mensaje, $m)) {
            return $m[0];
        }

        $digitos = preg_replace('/\D/', '', $mensaje);

        return strlen($digitos) === 8 && ! preg_match('/\pL/u', $mensaje) ? $digitos : null;
    }

    // Recibe el texto normalizado. Acepta el nombre completo o una palabra que lo inicie ("cardio")
    // o que comparta sus primeras letras ("pediatra" → Pediatría). Si dos empatan, no elige ninguna.
    public function especialidad(string $texto): ?Especialidad
    {
        $mejor = null;
        $mejorPuntaje = 0;
        $empate = false;

        foreach (Especialidad::where('estado', 1)->orderBy('nombre')->get() as $especialidad) {
            $nombre = NormalizadorTexto::normalizar($especialidad->nombre);
            $puntaje = str_contains(" {$texto} ", " {$nombre} ") ? 100 : $this->prefijoComun($texto, $nombre);

            if ($puntaje > $mejorPuntaje) {
                [$mejor, $mejorPuntaje, $empate] = [$especialidad, $puntaje, false];
            } elseif ($puntaje > 0 && $puntaje === $mejorPuntaje) {
                $empate = true;
            }
        }

        return $empate ? null : $mejor;
    }

    // Recibe el texto normalizado. Fuera del paso de elegir médico se exige "dr/dra/doctor/doctora"
    // antes del nombre, para que palabras como "paz" o "luna" no se tomen por apellidos.
    public function medico(string $texto, bool $sinTitulo = false, ?int $especialidadId = null): ?Medico
    {
        if ($sinTitulo) {
            $palabras = explode(' ', $texto);
        } elseif (preg_match('/\b(?:dr|dra|doctor|doctora)\b\s*(\w+)(?: (\w+))?/', $texto, $m)) {
            $palabras = array_slice($m, 1);
        } else {
            return null;
        }
        $palabras = array_filter($palabras, fn ($p) => strlen($p) >= 3);

        $encontrados = Medico::activos()
            ->when($especialidadId, fn ($q) => $q->where('especialidad_id', $especialidadId))
            ->get()
            ->filter(function (Medico $medico) use ($palabras) {
                $nombres = explode(' ', NormalizadorTexto::normalizar("{$medico->nombres} {$medico->apellidos}"));

                return array_intersect($palabras, $nombres) !== [];
            });

        return $encontrados->count() === 1 ? $encontrados->first() : null;
    }

    // Puntaje por la palabra del mensaje (de 5 letras o más) que mejor coincide con el inicio del nombre
    private function prefijoComun(string $texto, string $nombre): int
    {
        $puntaje = 0;

        foreach (explode(' ', $texto) as $palabra) {
            if (strlen($palabra) < 5) {
                continue;
            }
            if (str_starts_with($nombre, $palabra)) {
                $puntaje = max($puntaje, strlen($palabra));

                continue;
            }
            $comun = strspn($palabra ^ $nombre, "\0");
            if ($comun >= self::PREFIJO_ESPECIALIDAD) {
                $puntaje = max($puntaje, $comun);
            }
        }

        return $puntaje;
    }
}
