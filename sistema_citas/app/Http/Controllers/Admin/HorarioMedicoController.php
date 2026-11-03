<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Medico;
use App\Services\HorarioService;
use Illuminate\Http\Request;

// Mantenimiento: el Administrador edita los horarios de atención de cualquier médico
class HorarioMedicoController extends Controller
{
    public function edit(Medico $medico)
    {
        return view('admin.medicos.horarios', [
            'medico' => $medico->load('especialidad'),
            'dias' => HorarioService::datosFormulario($medico),
        ]);
    }

    public function update(Request $request, Medico $medico)
    {
        [$bloques, $fueraDeHorario] = HorarioService::guardar($medico, HorarioService::validar($request));

        Auditoria::registrar("Actualizó los horarios del médico #{$medico->id}", 'horarios_medicos', $medico->id);

        $redireccion = redirect()->route('admin.medicos.horarios.edit', $medico)
            ->with('exito', "Horarios de Dr(a). {$medico->nombres} {$medico->apellidos} guardados.");

        if ($bloques === 0) {
            $redireccion->with('aviso', 'No marcaste ningún día de atención: no se le podrán agendar citas.');
        } elseif ($fueraDeHorario > 0) {
            $redireccion->with('aviso', "{$fueraDeHorario} cita(s) futura(s) quedan fuera del nuevo horario. No se cancelaron.");
        }

        return $redireccion;
    }
}
