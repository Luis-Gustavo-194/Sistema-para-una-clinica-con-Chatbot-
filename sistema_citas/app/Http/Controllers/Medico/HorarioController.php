<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Concerns\PerfilActual;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Services\HorarioService;
use Illuminate\Http\Request;

class HorarioController extends Controller
{
    use PerfilActual;

    // Formulario semanal: por día, un bloque principal y un segundo bloque opcional (p. ej. mañana y tarde)
    public function edit()
    {
        $dias = HorarioService::datosFormulario($this->medicoActual());

        return view('medico.horarios', compact('dias'));
    }

    // Reemplaza los horarios del médico. Avisa (sin cancelar) las citas futuras que quedan fuera.
    public function update(Request $request)
    {
        $medico = $this->medicoActual();
        [$bloques, $fueraDeHorario] = HorarioService::guardar($medico, HorarioService::validar($request));

        Auditoria::registrar('Actualizó sus horarios de atención', 'horarios_medicos', $medico->id);

        $redireccion = redirect()->route('medico.horarios.edit')->with('exito', 'Horarios guardados. Los pacientes ya ven los nuevos turnos.');

        if ($bloques === 0) {
            $redireccion->with('aviso', 'No marcaste ningún día de atención: no se te podrán agendar citas.');
        } elseif ($fueraDeHorario > 0) {
            $redireccion->with('aviso', "{$fueraDeHorario} cita(s) futura(s) quedan fuera del nuevo horario. No se cancelaron: revísalas en «Mis citas».");
        }

        return $redireccion;
    }
}
