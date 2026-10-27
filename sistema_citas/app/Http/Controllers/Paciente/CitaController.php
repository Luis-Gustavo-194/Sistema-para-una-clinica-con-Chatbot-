<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerfilActual;
use Illuminate\Http\Request;
use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;
use App\Services\CitaService;
use App\Services\DisponibilidadService;

class CitaController extends Controller
{
    use PerfilActual;

    // Pestañas: Próximas (se pueden cancelar/reprogramar) e Historial (todo lo demás)
    public function index()
    {
        $paciente = $this->pacienteActual();

        $citas = Cita::with('medico.especialidad')
            ->where('paciente_id', $paciente->id)
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->get();

        [$proximas, $historial] = $citas->partition(fn ($cita) => $cita->esModificable());

        return view('paciente.citas.index', [
            'proximas' => $proximas->sortBy(fn ($c) => $c->fecha . $c->hora_inicio),
            'historial' => $historial,
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
        [$medico, $turno] = $this->turnoElegido($request);

        if (!$turno) {
            return back()->withInput()->withErrors(['turno' => 'El horario elegido ya no está disponible. Elige otro, por favor.']);
        }

        try {
            $cita = CitaService::guardar($this->datosCita($paciente, $medico, $turno));
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['turno' => $e->getMessage()]);
        }

        Auditoria::registrar("Agendó la cita #{$cita->id} desde su panel", 'citas', $cita->id);

        return redirect()->route('paciente.citas.index')
            ->with('exito', 'Cita agendada para el ' . date('d/m/Y', strtotime($turno['fecha'])) . ' a las ' . date('g:i A', strtotime($turno['inicio'])) . " con Dr(a). {$medico->apellidos}.");
    }

    // Reprogramar: el mismo selector, sin contar la propia cita como ocupada
    public function edit(Request $request, Cita $cita)
    {
        $paciente = $this->pacienteActual();
        $this->citaModificable($cita, $paciente);

        // Por defecto se muestra el mismo médico de la cita
        if (!$request->has('especialidad')) {
            $request->merge(['especialidad' => $cita->medico->especialidad_id, 'medico' => $cita->medico_id]);
        }

        return view('paciente.citas.form', $this->datosSelector($request, $cita->id) + ['cita' => $cita]);
    }

    public function update(Request $request, Cita $cita)
    {
        $paciente = $this->pacienteActual();
        $this->citaModificable($cita, $paciente);
        [$medico, $turno] = $this->turnoElegido($request, $cita->id);

        if (!$turno) {
            return back()->withInput()->withErrors(['turno' => 'El horario elegido ya no está disponible. Elige otro, por favor.']);
        }

        try {
            CitaService::guardar($this->datosCita($paciente, $medico, $turno), $cita);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['turno' => $e->getMessage()]);
        }

        Auditoria::registrar("Reprogramó la cita #{$cita->id} desde su panel", 'citas', $cita->id);

        return redirect()->route('paciente.citas.index')
            ->with('exito', 'Cita reprogramada para el ' . date('d/m/Y', strtotime($turno['fecha'])) . ' a las ' . date('g:i A', strtotime($turno['inicio'])) . '.');
    }

    public function cancelar(Cita $cita)
    {
        $paciente = $this->pacienteActual();
        $this->citaModificable($cita, $paciente);

        $cita->update(['estado' => 'Cancelada']);
        Auditoria::registrar("Canceló la cita #{$cita->id} desde su panel", 'citas', $cita->id);

        return redirect()->route('paciente.citas.index')->with('exito', 'Tu cita del ' . date('d/m/Y', strtotime($cita->fecha)) . ' fue cancelada.');
    }

    // Solo citas propias que aún se pueden cambiar (404 si es ajena, error si ya pasó/fue atendida)
    private function citaModificable(Cita $cita, Paciente $paciente): void
    {
        $this->citaDelPaciente($cita, $paciente);

        if (!$cita->esModificable()) {
            abort(redirect()->route('paciente.citas.index')->withErrors(['cita' => 'Esta cita ya no se puede modificar.']));
        }
    }

    // Opciones del selector según lo elegido (por GET): especialidades → médicos → días con turnos
    private function datosSelector(Request $request, ?int $excluirCitaId = null): array
    {
        $especialidades = Especialidad::where('estado', 1)->orderBy('nombre')->get();
        $especialidadId = $especialidades->firstWhere('id', (int) $request->query('especialidad', $request->input('especialidad')))?->id;

        $medicos = $especialidadId
            ? Medico::activos()->where('especialidad_id', $especialidadId)->orderBy('apellidos')->get()
            : collect();
        $medico = $medicos->firstWhere('id', (int) $request->query('medico', $request->input('medico')));

        $dias = $medico ? DisponibilidadService::proximosDias($medico, DisponibilidadService::DIAS_BUSQUEDA, $excluirCitaId) : [];
        $sinHorarios = $medico && !DisponibilidadService::tieneHorarios($medico);

        return compact('especialidades', 'especialidadId', 'medicos', 'medico', 'dias', 'sinHorarios');
    }

    // Valida en el servidor el turno enviado ("Y-m-d|H:i:s") contra la disponibilidad real
    private function turnoElegido(Request $request, ?int $excluirCitaId = null): array
    {
        $datos = $request->validate([
            'medico' => ['required', 'integer'],
            'turno' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}\|\d{2}:\d{2}:\d{2}$/'],
        ]);

        $medico = Medico::activos()
            ->where('id', $datos['medico'])
            ->whereHas('especialidad', fn ($q) => $q->where('estado', 1))
            ->first();

        if (!$medico) {
            return [null, null];
        }

        [$fecha, $inicio] = explode('|', $datos['turno']);
        $turno = DisponibilidadService::esTurnoValido($medico, $fecha, $inicio, $excluirCitaId);

        return [$medico, $turno ? $turno + ['fecha' => $fecha] : null];
    }

    private function datosCita(Paciente $paciente, Medico $medico, array $turno): array
    {
        return [
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'fecha' => $turno['fecha'],
            'hora_inicio' => $turno['inicio'],
            'hora_fin' => $turno['fin'],
        ];
    }
}
