<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerfilActual;
use Illuminate\Support\Carbon;
use App\Models\Cita;
use App\Models\HistoriaClinica;
use App\Services\DisponibilidadService;

class PanelController extends Controller
{
    use PerfilActual;

    // Inicio: próxima cita destacada, resumen y últimas atenciones
    public function index()
    {
        $paciente = $this->pacienteActual();

        $proxima = Cita::with('medico.especialidad')
            ->modificables()
            ->where('paciente_id', $paciente->id)
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->first();

        $consultorio = $proxima ? DisponibilidadService::consultorio($proxima->medico, $proxima->fecha) : null;
        $diasFaltan = $proxima ? (int) today()->diffInDays(Carbon::parse($proxima->fecha)) : null;

        $resumen = [
            'proximas' => Cita::modificables()->where('paciente_id', $paciente->id)->count(),
            'atendidas' => Cita::where('paciente_id', $paciente->id)->where('estado', 'Atendida')->count(),
            'inasistencias' => Cita::where('paciente_id', $paciente->id)->where('estado', 'No Asistio')->count(),
        ];

        $ultimasAtenciones = $this->historias($paciente->id)->take(3)->get();

        return view('paciente.inicio', compact('paciente', 'proxima', 'consultorio', 'diasFaltan', 'resumen', 'ultimasAtenciones'));
    }

    // Historia clínica: atenciones registradas por los médicos (solo lectura)
    public function historia()
    {
        $paciente = $this->pacienteActual();
        $historias = $this->historias($paciente->id)->get();

        return view('paciente.historia', compact('historias'));
    }

    private function historias(int $pacienteId)
    {
        return HistoriaClinica::with('cita.medico.especialidad')
            ->join('citas', 'citas.id', '=', 'historias_clinicas.cita_id')
            ->where('citas.paciente_id', $pacienteId)
            ->orderByDesc('citas.fecha')
            ->select('historias_clinicas.*');
    }
}
