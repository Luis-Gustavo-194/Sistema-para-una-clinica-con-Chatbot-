<?php

namespace App\Chatbot\Ejecutores;

use App\Chatbot\Respuesta;
use App\Models\Cita;
use App\Models\Medico;
use App\Models\Rol;
use Illuminate\Support\Facades\Auth;

// Solo un médico con sesión iniciada puede ver su propia agenda
class ConsultarAgendaMedico extends EjecutorSimple
{
    protected function responder(): Respuesta
    {
        $usuario = Auth::user();
        $medico = ($usuario && (int) $usuario->rol_id === Rol::MEDICO && (int) $usuario->estado === 1)
            ? Medico::where('usuario_id', $usuario->id)->first()
            : null;

        if (! $medico) {
            return new Respuesta('Por seguridad, para consultar la agenda médica debes iniciar sesión en el portal con tus credenciales.');
        }

        $citas = Cita::with('paciente')
            ->noCanceladas()
            ->where('medico_id', $medico->id)
            ->where('fecha', '>=', today()->toDateString())
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        if ($citas->isEmpty()) {
            return new Respuesta("Dr. {$medico->apellidos}, no tiene citas programadas en su agenda para los próximos días.");
        }

        $texto = "Dr. {$medico->apellidos}, aquí tiene sus próximas citas agendadas:\n\n";
        foreach ($citas as $cita) {
            $texto .= '📅 Fecha: '.date('d/m/Y', strtotime($cita->fecha))."\n";
            $texto .= '⏰ Hora: '.date('h:i A', strtotime($cita->hora_inicio))."\n";
            $texto .= "👤 Paciente: {$cita->paciente->nombres} {$cita->paciente->apellidos}\n────────────────\n";
        }

        return new Respuesta(trim($texto));
    }
}
