<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerfilActual;
use App\Models\Cita;
use App\Services\DisponibilidadService;

class PanelController extends Controller
{
    use PerfilActual;

    // Agenda de hoy + indicadores personales del médico
    public function index()
    {
        $medico = $this->medicoActual();
        $hoy = today();
        $inicioMes = $hoy->copy()->startOfMonth()->toDateString();
        $finMes = $hoy->copy()->endOfMonth()->toDateString();

        $agenda = Cita::with(['paciente', 'historiaClinica'])
            ->noCanceladas()
            ->where('medico_id', $medico->id)
            ->where('fecha', $hoy->toDateString())
            ->orderBy('hora_inicio')
            ->get();

        $delMes = Cita::where('medico_id', $medico->id)->whereBetween('fecha', [$inicioMes, $finMes]);
        $atendidasMes = (clone $delMes)->where('estado', 'Atendida')->count();
        $noAsistioMes = (clone $delMes)->where('estado', 'No Asistio')->count();

        $kpis = [
            'citas_hoy' => $agenda->count(),
            'por_atender_hoy' => $agenda->filter(fn ($cita) => in_array($cita->estado ?? 'Pendiente', ['Pendiente', 'Confirmada'], true))->count(),
            'citas_semana' => Cita::noCanceladas()
                ->where('medico_id', $medico->id)
                ->whereBetween('fecha', [$hoy->copy()->startOfWeek()->toDateString(), $hoy->copy()->endOfWeek()->toDateString()])
                ->count(),
            'atendidas_mes' => $atendidasMes,
            'tasa_inasistencia' => ($atendidasMes + $noAsistioMes) > 0 ? round($noAsistioMes * 100 / ($atendidasMes + $noAsistioMes), 1) : null,
            'pacientes_mes' => (clone $delMes)->noCanceladas()->distinct()->count('paciente_id'),
        ];

        $proximas = Cita::with('paciente')
            ->noCanceladas()
            ->where('medico_id', $medico->id)
            ->where('fecha', '>', $hoy->toDateString())
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->take(5)
            ->get();

        $sinHorarios = !DisponibilidadService::tieneHorarios($medico);

        return view('medico.panel', compact('medico', 'agenda', 'kpis', 'proximas', 'sinHorarios'));
    }
}
