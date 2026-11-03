<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;
use App\Services\DisponibilidadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// Selector especialidad → médico → turno libre que comparten el panel del paciente y recepción
// (vista partials/selector-turno). La reserva final siempre pasa por CitaService.
trait SeleccionTurno
{
    // Opciones del selector según lo elegido (por GET): especialidades → médicos → días con turnos
    protected function datosSelector(Request $request, ?int $excluirCitaId = null): array
    {
        $especialidades = Especialidad::where('estado', 1)->orderBy('nombre')->get();
        $especialidadId = $especialidades->firstWhere('id', (int) $request->query('especialidad', $request->input('especialidad')))?->id;

        $medicos = $especialidadId
            ? Medico::activos()->where('especialidad_id', $especialidadId)->orderBy('apellidos')->get()
            : collect();
        $medico = $medicos->firstWhere('id', (int) $request->query('medico', $request->input('medico')));

        $dias = $medico ? DisponibilidadService::proximosDias($medico, DisponibilidadService::DIAS_BUSQUEDA, $excluirCitaId) : [];
        $sinHorarios = $medico && ! DisponibilidadService::tieneHorarios($medico);

        return compact('especialidades', 'especialidadId', 'medicos', 'medico', 'dias', 'sinHorarios');
    }

    // Valida en el servidor el turno enviado ("Y-m-d|H:i:s") contra la disponibilidad real.
    // Devuelve [médico, turno|null, fecha pedida, hora pedida].
    protected function turnoElegido(Request $request, ?int $excluirCitaId = null): array
    {
        $datos = $request->validate([
            'medico' => ['required', 'integer'],
            'turno' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}\|\d{2}:\d{2}:\d{2}$/'],
        ]);

        $medico = Medico::activos()
            ->where('id', $datos['medico'])
            ->whereHas('especialidad', fn ($q) => $q->where('estado', 1))
            ->first();

        [$fecha, $inicio] = explode('|', $datos['turno']);

        if (! $medico) {
            return [null, null, $fecha, $inicio];
        }

        $turno = DisponibilidadService::esTurnoValido($medico, $fecha, $inicio, $excluirCitaId);

        return [$medico, $turno ? $turno + ['fecha' => $fecha] : null, $fecha, $inicio];
    }

    // Conflicto de horario: vuelve al formulario con los horarios recargados (mismo médico)
    // y los turnos libres más cercanos al que se pidió.
    protected function volverPorConflicto(string $ruta, array $parametros, ?Medico $medico, string $fecha, string $inicio, string $mensaje, ?int $excluirCitaId = null): RedirectResponse
    {
        $alternativas = $medico ? DisponibilidadService::alternativas($medico, $fecha, $inicio, 3, $excluirCitaId) : [];

        return redirect()
            ->route($ruta, $parametros + ($medico ? ['especialidad' => $medico->especialidad_id, 'medico' => $medico->id] : []))
            ->withErrors(['turno' => $mensaje.($alternativas ? ' Te mostramos los horarios libres más cercanos.' : '')])
            ->with('alternativas', $alternativas);
    }

    protected function datosCita(Paciente $paciente, Medico $medico, array $turno): array
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
