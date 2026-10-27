<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use App\Models\Cita;
use App\Models\HorarioMedico;
use App\Models\Medico;

// Calcula los turnos libres de un médico a partir de sus horarios (horarios_medicos)
// y de las citas que ya tiene. Lo usan el chatbot y el panel del paciente.
class DisponibilidadService
{
    // Cuántos días hacia adelante (desde mañana) se pueden reservar turnos
    const DIAS_BUSQUEDA = 14;

    // Turnos libres de un día: [['inicio' => 'H:i:s', 'fin' => 'H:i:s', 'consultorio' => ...], ...]
    public static function turnosDelDia(Medico $medico, CarbonInterface $fecha, ?int $excluirCitaId = null): array
    {
        return self::proximosDiasEntre($medico, $fecha, $fecha, $excluirCitaId)[$fecha->toDateString()] ?? [];
    }

    // Días (desde mañana) que tienen turnos libres: ['Y-m-d' => turnos, ...]
    public static function proximosDias(Medico $medico, int $dias = self::DIAS_BUSQUEDA, ?int $excluirCitaId = null): array
    {
        return self::proximosDiasEntre($medico, today()->addDay(), today()->addDays($dias), $excluirCitaId);
    }

    // Comprueba en el servidor que el turno elegido existe y sigue libre. Devuelve el turno o null.
    public static function esTurnoValido(Medico $medico, string $fecha, string $inicio, ?int $excluirCitaId = null): ?array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return null;
        }
        $dia = Carbon::createFromFormat('Y-m-d', $fecha)->startOfDay();
        if ($dia->toDateString() !== $fecha || $dia->lte(today()) || $dia->gt(today()->addDays(self::DIAS_BUSQUEDA))) {
            return null;
        }

        foreach (self::turnosDelDia($medico, $dia, $excluirCitaId) as $turno) {
            if ($turno['inicio'] === $inicio) {
                return $turno;
            }
        }
        return null;
    }

    public static function tieneHorarios(Medico $medico): bool
    {
        return HorarioMedico::where('medico_id', $medico->id)->exists();
    }

    // Consultorio del médico para una fecha (según su horario de ese día de la semana)
    public static function consultorio(Medico $medico, string $fecha): ?string
    {
        return HorarioMedico::where('medico_id', $medico->id)
            ->where('dia_semana', Carbon::parse($fecha)->dayOfWeekIso)
            ->value('consultorio');
    }

    // Carga horarios y citas una sola vez para todo el rango y genera los turnos de cada día
    private static function proximosDiasEntre(Medico $medico, CarbonInterface $desde, CarbonInterface $hasta, ?int $excluirCitaId): array
    {
        $bloquesPorDia = HorarioMedico::where('medico_id', $medico->id)
            ->orderBy('hora_inicio')
            ->get()
            ->groupBy('dia_semana');

        if ($bloquesPorDia->isEmpty()) {
            return [];
        }

        $ocupadasPorFecha = Cita::noCanceladas()
            ->where('medico_id', $medico->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->when($excluirCitaId, fn ($q) => $q->where('id', '!=', $excluirCitaId))
            ->get(['fecha', 'hora_inicio', 'hora_fin'])
            ->groupBy(fn ($cita) => Carbon::parse($cita->fecha)->toDateString());

        $resultado = [];
        for ($dia = $desde->copy()->startOfDay(); $dia->lte($hasta); $dia->addDay()) {
            $bloques = $bloquesPorDia->get($dia->dayOfWeekIso);
            if (!$bloques) {
                continue;
            }

            $ocupadas = ($ocupadasPorFecha->get($dia->toDateString()) ?? collect())
                ->map(fn ($cita) => [self::segundos($cita->hora_inicio), self::segundos($cita->hora_fin)]);

            $turnos = [];
            foreach ($bloques as $bloque) {
                $duracion = max(5, (int) ($bloque->duracion_consulta_minutos ?: 30)) * 60;
                $finBloque = self::segundos($bloque->hora_fin);

                for ($inicio = self::segundos($bloque->hora_inicio); $inicio + $duracion <= $finBloque; $inicio += $duracion) {
                    $fin = $inicio + $duracion;
                    // Libre si no se cruza con ninguna cita existente
                    $choca = $ocupadas->contains(fn ($o) => $o[0] < $fin && $o[1] > $inicio);
                    if (!$choca) {
                        $turnos[] = ['inicio' => self::hora($inicio), 'fin' => self::hora($fin), 'consultorio' => $bloque->consultorio];
                    }
                }
            }

            if ($turnos) {
                $resultado[$dia->toDateString()] = $turnos;
            }
        }

        return $resultado;
    }

    private static function segundos(string $hora): int
    {
        [$h, $m, $s] = array_map('intval', explode(':', $hora)) + [0, 0, 0];
        return $h * 3600 + $m * 60 + $s;
    }

    private static function hora(int $segundos): string
    {
        return sprintf('%02d:%02d:00', intdiv($segundos, 3600), intdiv($segundos % 3600, 60));
    }
}
