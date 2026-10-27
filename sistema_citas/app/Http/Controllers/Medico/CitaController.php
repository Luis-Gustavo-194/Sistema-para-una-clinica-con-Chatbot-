<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerfilActual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\HistoriaClinica;

class CitaController extends Controller
{
    use PerfilActual;

    // Lista de citas del médico: pestañas Próximas (desde hoy) / Historial (pasadas), con filtros
    public function index(Request $request)
    {
        $medico = $this->medicoActual();
        $pestana = $request->query('pestana') === 'historial' ? 'historial' : 'proximas';
        $estado = $request->query('estado');
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');
        $hoy = today()->toDateString();

        $citas = Cita::with(['paciente', 'historiaClinica'])
            ->where('medico_id', $medico->id)
            ->when($pestana === 'proximas', fn ($q) => $q->where('fecha', '>=', $hoy)->orderBy('fecha')->orderBy('hora_inicio'))
            ->when($pestana === 'historial', fn ($q) => $q->where('fecha', '<', $hoy)->orderByDesc('fecha')->orderByDesc('hora_inicio'))
            ->when(in_array($estado, ['Pendiente', 'Confirmada', 'Atendida', 'No Asistio', 'Cancelada'], true), fn ($q) => $estado === 'Pendiente'
                ? $q->where(fn ($q) => $q->where('estado', 'Pendiente')->orWhereNull('estado'))
                : $q->where('estado', $estado))
            ->when($this->esFecha($desde), fn ($q) => $q->where('fecha', '>=', $desde))
            ->when($this->esFecha($hasta), fn ($q) => $q->where('fecha', '<=', $hasta))
            ->paginate(15)
            ->withQueryString();

        return view('medico.citas.index', compact('citas', 'pestana', 'estado', 'desde', 'hasta'));
    }

    // Detalle de la cita: datos del paciente, su historial en la clínica y el formulario de atención
    public function show(Cita $cita)
    {
        $medico = $this->medicoActual();
        $this->citaDelMedico($cita, $medico)->load(['paciente', 'historiaClinica']);

        $historial = Cita::with(['medico.especialidad', 'historiaClinica'])
            ->where('paciente_id', $cita->paciente_id)
            ->where('id', '!=', $cita->id)
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->get();

        $puedeAtender = $this->puedeAtender($cita);

        return view('medico.citas.show', compact('cita', 'historial', 'puedeAtender'));
    }

    // Confirmar (citas por venir) o marcar "No asistió" (citas de hoy o pasadas)
    public function cambiarEstado(Request $request, Cita $cita)
    {
        $medico = $this->medicoActual();
        $this->citaDelMedico($cita, $medico);

        $nuevo = $request->validate(['estado' => ['required', Rule::in(['Confirmada', 'No Asistio'])]])['estado'];
        $actual = $cita->estado ?? 'Pendiente';
        $hoy = today()->toDateString();

        $permitido = match ($nuevo) {
            'Confirmada' => $actual === 'Pendiente' && $cita->fecha >= $hoy,
            'No Asistio' => in_array($actual, ['Pendiente', 'Confirmada'], true) && $cita->fecha <= $hoy,
        };

        if (!$permitido) {
            return back()->withErrors(['estado' => "No se puede cambiar una cita en estado «{$cita->estado_texto}» a «" . ($nuevo === 'No Asistio' ? 'No asistió' : $nuevo) . "» en esta fecha."]);
        }

        $cita->update(['estado' => $nuevo]);
        Auditoria::registrar("Marcó la cita #{$cita->id} como {$cita->estado_texto}", 'citas', $cita->id);

        return back()->with('exito', "Cita de las " . date('g:i A', strtotime($cita->hora_inicio)) . " marcada como «{$cita->estado_texto}».");
    }

    // Registra (o corrige) la historia clínica y deja la cita como Atendida
    public function atender(Request $request, Cita $cita)
    {
        $medico = $this->medicoActual();
        $this->citaDelMedico($cita, $medico);

        if (!$this->puedeAtender($cita)) {
            return back()->withErrors(['cita' => 'Solo se pueden atender citas de hoy o pasadas que no estén canceladas.']);
        }

        $datos = $request->validate([
            'diagnostico' => ['required', 'string', 'max:5000'],
            'tratamiento' => ['nullable', 'string', 'max:5000'],
            'receta_medica' => ['nullable', 'string', 'max:5000'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ]);

        $esNueva = !$cita->historiaClinica()->exists();

        DB::transaction(function () use ($cita, $datos) {
            HistoriaClinica::updateOrCreate(['cita_id' => $cita->id], $datos);
            $cita->update(['estado' => 'Atendida']);
        });

        Auditoria::registrar(($esNueva ? 'Registró' : 'Actualizó') . " la atención de la cita #{$cita->id}", 'historias_clinicas', $cita->id);

        return redirect()->route('medico.citas.show', $cita)
            ->with('exito', $esNueva ? 'Atención registrada. La cita quedó como «Atendida».' : 'Historia clínica actualizada.');
    }

    private function puedeAtender(Cita $cita): bool
    {
        return $cita->fecha <= today()->toDateString() && $cita->estado !== 'Cancelada';
    }

    private function esFecha(?string $valor): bool
    {
        return $valor !== null && (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor);
    }
}
