<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Concerns\PerfilActual;
use App\Http\Controllers\Concerns\SeleccionTurno;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\Paciente;
use App\Services\CitaService;
use Illuminate\Http\Request;

class CitaController extends Controller
{
    use PerfilActual;
    use SeleccionTurno;

    // Pestañas: Próximas (se pueden cancelar/reprogramar) e Historial (todo lo demás), con filtro por estado
    public function index(Request $request)
    {
        $paciente = $this->pacienteActual();
        $estado = array_key_exists((string) $request->query('estado'), Cita::ESTADOS) ? $request->query('estado') : null;

        $citas = Cita::with('medico.especialidad')
            ->where('paciente_id', $paciente->id)
            ->when($estado === 'Pendiente', fn ($q) => $q->where(fn ($q) => $q->where('estado', 'Pendiente')->orWhereNull('estado')))
            ->when($estado && $estado !== 'Pendiente', fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->get();

        [$proximas, $historial] = $citas->partition(fn ($cita) => $cita->esModificable());

        return view('paciente.citas.index', [
            'proximas' => $proximas->sortBy(fn ($c) => $c->fecha.$c->hora_inicio),
            'historial' => $historial,
            'estado' => $estado,
        ]);
    }

    // Agendar: selector especialidad → médico → turno libre
    public function create(Request $request)
    {
        $this->pacienteActual();

        return view('paciente.citas.form', $this->datosSelector($request) + ['cita' => null]);
    }

    public function store(Request $request)
    {
        $paciente = $this->pacienteActual();
        [$medico, $turno, $fecha, $inicio] = $this->turnoElegido($request);

        if (! $turno) {
            return $this->volverPorConflicto('paciente.citas.create', [], $medico, $fecha, $inicio, 'El horario elegido ya no está disponible.');
        }

        try {
            $cita = CitaService::guardar($this->datosCita($paciente, $medico, $turno), canal: 'web');
        } catch (\DomainException $e) {
            return $this->volverPorConflicto('paciente.citas.create', [], $medico, $fecha, $inicio, $e->getMessage());
        }

        Auditoria::registrar("Agendó la cita #{$cita->id} desde su panel", 'citas', $cita->id);

        return redirect()->route('paciente.citas.index')
            ->with('exito', 'Cita agendada para el '.date('d/m/Y', strtotime($turno['fecha'])).' a las '.date('g:i A', strtotime($turno['inicio']))." con Dr(a). {$medico->apellidos}.");
    }

    // Reprogramar: el mismo selector, sin contar la propia cita como ocupada
    public function edit(Request $request, Cita $cita)
    {
        $paciente = $this->pacienteActual();
        $this->citaModificable($cita, $paciente);

        // Por defecto se muestra el mismo médico de la cita
        if (! $request->has('especialidad')) {
            $request->merge(['especialidad' => $cita->medico->especialidad_id, 'medico' => $cita->medico_id]);
        }

        return view('paciente.citas.form', $this->datosSelector($request, $cita->id) + ['cita' => $cita]);
    }

    public function update(Request $request, Cita $cita)
    {
        $paciente = $this->pacienteActual();
        $this->citaModificable($cita, $paciente);
        [$medico, $turno, $fecha, $inicio] = $this->turnoElegido($request, $cita->id);

        if (! $turno) {
            return $this->volverPorConflicto('paciente.citas.edit', ['cita' => $cita], $medico, $fecha, $inicio, 'El horario elegido ya no está disponible.', $cita->id);
        }

        try {
            CitaService::guardar($this->datosCita($paciente, $medico, $turno), $cita);
        } catch (\DomainException $e) {
            return $this->volverPorConflicto('paciente.citas.edit', ['cita' => $cita], $medico, $fecha, $inicio, $e->getMessage(), $cita->id);
        }

        Auditoria::registrar("Reprogramó la cita #{$cita->id} desde su panel", 'citas', $cita->id);

        return redirect()->route('paciente.citas.index')
            ->with('exito', 'Cita reprogramada para el '.date('d/m/Y', strtotime($turno['fecha'])).' a las '.date('g:i A', strtotime($turno['inicio'])).'.');
    }

    // Respeta la anticipación mínima (config clinica.cancelacion_horas_minimas); el turno queda libre
    public function cancelar(Cita $cita)
    {
        $paciente = $this->pacienteActual();
        $this->citaModificable($cita, $paciente);

        try {
            CitaService::cancelar($cita);
        } catch (\DomainException $e) {
            return redirect()->route('paciente.citas.index')->withErrors(['cita' => $e->getMessage()]);
        }

        Auditoria::registrar("Canceló la cita #{$cita->id} desde su panel", 'citas', $cita->id);

        return redirect()->route('paciente.citas.index')->with('exito', 'Tu cita del '.date('d/m/Y', strtotime($cita->fecha)).' fue cancelada.');
    }

    // Solo citas propias que aún se pueden cambiar (404 si es ajena, error si ya pasó/fue atendida)
    private function citaModificable(Cita $cita, Paciente $paciente): void
    {
        $this->citaDelPaciente($cita, $paciente);

        if (! $cita->esModificable()) {
            abort(redirect()->route('paciente.citas.index')->withErrors(['cita' => 'Esta cita ya no se puede modificar.']));
        }
    }
}
