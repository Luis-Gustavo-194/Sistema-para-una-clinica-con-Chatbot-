<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerfilActual;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\HorarioMedico;

class HorarioController extends Controller
{
    use PerfilActual;

    // Formulario semanal: por día, un bloque principal y un segundo bloque opcional (p. ej. mañana y tarde)
    public function edit()
    {
        $medico = $this->medicoActual();
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

        return view('medico.horarios', compact('dias'));
    }

    // Reemplaza los horarios del médico. Avisa (sin cancelar) las citas futuras que quedan fuera.
    public function update(Request $request)
    {
        $medico = $this->medicoActual();

        $reglas = [];
        $nombres = [];
        foreach (HorarioMedico::DIAS as $numero => $nombre) {
            if (!$request->boolean("dias.{$numero}.activo")) {
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

        $datos = $request->validate($reglas, [], $nombres);

        // Filas nuevas: un registro por bloque
        $filas = [];
        foreach ($datos['dias'] ?? [] as $numero => $dia) {
            $comunes = ['medico_id' => $medico->id, 'dia_semana' => $numero, 'duracion_consulta_minutos' => (int) $dia['duracion'], 'consultorio' => $dia['consultorio'] ?? null];
            $filas[] = $comunes + ['hora_inicio' => $dia['inicio'] . ':00', 'hora_fin' => $dia['fin'] . ':00'];
            if (!empty($dia['inicio2']) && !empty($dia['fin2'])) {
                $filas[] = $comunes + ['hora_inicio' => $dia['inicio2'] . ':00', 'hora_fin' => $dia['fin2'] . ':00'];
            }
        }

        DB::transaction(function () use ($medico, $filas) {
            HorarioMedico::where('medico_id', $medico->id)->delete();
            foreach ($filas as $fila) {
                HorarioMedico::create($fila);
            }
        });

        Auditoria::registrar('Actualizó sus horarios de atención', 'horarios_medicos', $medico->id);

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

        $redireccion = redirect()->route('medico.horarios.edit')->with('exito', 'Horarios guardados. Los pacientes ya ven los nuevos turnos.');

        if (empty($filas)) {
            $redireccion->with('aviso', 'No marcaste ningún día de atención: no se te podrán agendar citas.');
        } elseif ($fueraDeHorario > 0) {
            $redireccion->with('aviso', "{$fueraDeHorario} cita(s) futura(s) quedan fuera del nuevo horario. No se cancelaron: revísalas en «Mis citas».");
        }

        return $redireccion;
    }
}
