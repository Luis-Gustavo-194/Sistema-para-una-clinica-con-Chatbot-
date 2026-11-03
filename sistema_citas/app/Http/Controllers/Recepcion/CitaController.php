<?php

namespace App\Http\Controllers\Recepcion;

use App\Http\Controllers\Concerns\SeleccionTurno;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\Paciente;
use App\Services\CitaService;
use Illuminate\Http\Request;

// Recepción reserva y cancela citas en nombre del paciente con el mismo servicio
// que usan la web del paciente y el chatbot (CitaService), con canal 'recepcion'.
class CitaController extends Controller
{
    use SeleccionTurno;

    public function create(Request $request, Paciente $paciente)
    {
        return view('recepcion.citas.form', $this->datosSelector($request) + ['paciente' => $paciente]);
    }

    public function store(Request $request, Paciente $paciente)
    {
        [$medico, $turno, $fecha, $inicio] = $this->turnoElegido($request);
        $ruta = 'recepcion.pacientes.citas.create';

        if (! $turno) {
            return $this->volverPorConflicto($ruta, ['paciente' => $paciente], $medico, $fecha, $inicio, 'El horario elegido ya no está disponible.');
        }

        try {
            $cita = CitaService::guardar($this->datosCita($paciente, $medico, $turno), canal: 'recepcion');
        } catch (\DomainException $e) {
            return $this->volverPorConflicto($ruta, ['paciente' => $paciente], $medico, $fecha, $inicio, $e->getMessage());
        }

        Auditoria::registrar("Agendó la cita #{$cita->id} para el paciente #{$paciente->id} desde recepción", 'citas', $cita->id);

        return redirect()->route('recepcion.pacientes.show', $paciente)
            ->with('exito', 'Cita reservada para el '.date('d/m/Y', strtotime($turno['fecha'])).' a las '.date('g:i A', strtotime($turno['inicio']))." con Dr(a). {$medico->apellidos}.");
    }

    // Recepción puede cancelar aunque falten menos horas que la anticipación mínima del paciente
    public function cancelar(Cita $cita)
    {
        try {
            CitaService::cancelar($cita, forzar: true);
        } catch (\DomainException $e) {
            return back()->withErrors(['cita' => $e->getMessage()]);
        }

        Auditoria::registrar("Canceló la cita #{$cita->id} del paciente #{$cita->paciente_id} desde recepción", 'citas', $cita->id);

        return redirect()->route('recepcion.pacientes.show', $cita->paciente_id)
            ->with('exito', 'Cita del '.date('d/m/Y', strtotime($cita->fecha)).' cancelada. El horario quedó libre.');
    }
}
