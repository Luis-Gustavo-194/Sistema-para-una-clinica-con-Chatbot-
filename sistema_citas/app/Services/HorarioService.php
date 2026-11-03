<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\HorarioMedico;
use App\Models\Medico;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Horario semanal de un médico: por día, un bloque principal y un segundo bloque opcional.
// Lo usan el propio médico (Medico\HorarioController) y el Administrador (Admin\HorarioMedicoController).
class HorarioService
{
    // Datos para el formulario (partials/form-horarios)
    public static function datosFormulario(Medico $medico): array
    {
        $porDia = HorarioMedico::where('medico_id', $medico->id)->orderBy('hora_inicio')->get()->groupBy('dia_semana');

        $dias = [];
        foreach (HorarioMedico::DIAS as $numero => $nombre) {
            $bloques = $porDia->get($numero, collect())->values();
            $dias[$numero] = [
                'nombre' => $nombre,
                'activo' => $bloques->isNotEmpty(),
                'inicio' => substr($bloques[0]->hora_inicio ?? '08:00', 0, 5),
                'fin' => substr($bloques[0]->hora_fin ?? '13:00', 0, 5),
                'inicio2' => isset($bloques[1]) ? substr($bloques[1]->hora_inicio, 0, 5) : '',
                'fin2' => isset($bloques[1]) ? substr($bloques[1]->hora_fin, 0, 5) : '',
                'duracion' => $bloques[0]->duracion_consulta_minutos ?? 30,
                'consultorio' => $bloques[0]->consultorio ?? '',
            ];
        }

        return $dias;
    }

    // Valida el formulario (solo los días marcados) y devuelve los datos validados
    public static function validar(Request $request): array
    {
        $reglas = [];
        $nombres = [];
        foreach (HorarioMedico::DIAS as $numero => $nombre) {
            if (! $request->boolean("dias.{$numero}.activo")) {
                continue;
            }
            $d = "dias.{$numero}";
            $reglas["{$d}.inicio"] = ['required', 'date_format:H:i'];
            $reglas["{$d}.fin"] = ['required', 'date_format:H:i', "after:{$d}.inicio"];
            $reglas["{$d}.inicio2"] = ['nullable', 'date_format:H:i', "required_with:{$d}.fin2", "after_or_equal:{$d}.fin"];
            $reglas["{$d}.fin2"] = ['nullable', 'date_format:H:i', "required_with:{$d}.inicio2", "after:{$d}.inicio2"];
            $reglas["{$d}.duracion"] = ['required', 'integer', 'between:10,120'];
            $reglas["{$d}.consultorio"] = ['nullable', 'string', 'max:50'];

            $nombres += [
                "{$d}.inicio" => "hora de inicio ({$nombre})",
                "{$d}.fin" => "hora de fin ({$nombre})",
                "{$d}.inicio2" => "inicio del segundo bloque ({$nombre})",
                "{$d}.fin2" => "fin del segundo bloque ({$nombre})",
                "{$d}.duracion" => "duración de consulta ({$nombre})",
                "{$d}.consultorio" => "consultorio ({$nombre})",
            ];
        }

        return $request->validate($reglas, [], $nombres);
    }

    // Reemplaza los horarios del médico. Devuelve [cantidad de bloques, citas futuras que quedan fuera]
    // (esas citas no se cancelan; solo se avisa).
    public static function guardar(Medico $medico, array $datos): array
    {
        $filas = [];
        foreach ($datos['dias'] ?? [] as $numero => $dia) {
            $comunes = ['medico_id' => $medico->id, 'dia_semana' => $numero, 'duracion_consulta_minutos' => (int) $dia['duracion'], 'consultorio' => $dia['consultorio'] ?? null];
            $filas[] = $comunes + ['hora_inicio' => $dia['inicio'].':00', 'hora_fin' => $dia['fin'].':00'];
            if (! empty($dia['inicio2']) && ! empty($dia['fin2'])) {
                $filas[] = $comunes + ['hora_inicio' => $dia['inicio2'].':00', 'hora_fin' => $dia['fin2'].':00'];
            }
        }

        DB::transaction(function () use ($medico, $filas) {
            HorarioMedico::where('medico_id', $medico->id)->delete();
            foreach ($filas as $fila) {
                HorarioMedico::create($fila);
            }
        });

        // Citas futuras que ya no caben en ningún bloque del nuevo horario
        $fueraDeHorario = Cita::noCanceladas()
            ->where('medico_id', $medico->id)
            ->where('fecha', '>=', today()->toDateString())
            ->get()
            ->filter(function ($cita) use ($filas) {
                $dia = Carbon::parse($cita->fecha)->dayOfWeekIso;
                foreach ($filas as $fila) {
                    if ($fila['dia_semana'] === $dia && $fila['hora_inicio'] <= $cita->hora_inicio && $fila['hora_fin'] >= $cita->hora_fin) {
                        return false;
                    }
                }

                return true;
            })
            ->count();

        return [count($filas), $fueraDeHorario];
    }

    // Resumen para el directorio público: [['dias' => 'Lun, Mié', 'horas' => '08:00–13:00', 'consultorio' => 'C-101'], ...]
    public static function resumen(iterable $horarios): array
    {
        $abreviados = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
        $grupos = [];

        foreach (collect($horarios)->sortBy(['dia_semana', 'hora_inicio']) as $bloque) {
            $horas = substr($bloque->hora_inicio, 0, 5).'–'.substr($bloque->hora_fin, 0, 5);
            $clave = $horas.'|'.$bloque->consultorio;
            $grupos[$clave]['horas'] = $horas;
            $grupos[$clave]['consultorio'] = $bloque->consultorio;
            $grupos[$clave]['dias'][] = $abreviados[$bloque->dia_semana] ?? $bloque->dia_semana;
        }

        return array_values(array_map(fn ($g) => ['dias' => implode(', ', array_unique($g['dias'])), 'horas' => $g['horas'], 'consultorio' => $g['consultorio']], $grupos));
    }
}
