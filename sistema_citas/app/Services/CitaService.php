<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Cita;

class CitaService
{
    // Crea una cita nueva o, si se pasa $cita, la reprograma.
    // En ambos casos valida choques de horario dentro de una transacción.
    // Lanza \DomainException con un mensaje legible si el horario no está disponible.
    public static function guardar(array $datos, ?Cita $cita = null): Cita
    {
        return DB::transaction(function () use ($datos, $cita) {
            // 1. El paciente no puede tener dos citas que se crucen en horario
            $choquePaciente = Cita::noCanceladas()
                                  ->where('paciente_id', $datos['paciente_id'])
                                  ->where('fecha', $datos['fecha'])
                                  ->where('hora_inicio', '<', $datos['hora_fin'])
                                  ->where('hora_fin', '>', $datos['hora_inicio'])
                                  ->when($cita, fn ($q) => $q->where('id', '!=', $cita->id))
                                  ->exists();

            if ($choquePaciente) {
                throw new \DomainException("El paciente ya tiene una cita reservada en ese mismo horario.");
            }

            // 2. Bloqueo pesimista para el horario del médico (cualquier cita que se cruce)
            $choqueMedico = Cita::noCanceladas()
                                ->where('medico_id', $datos['medico_id'])
                                ->where('fecha', $datos['fecha'])
                                ->where('hora_inicio', '<', $datos['hora_fin'])
                                ->where('hora_fin', '>', $datos['hora_inicio'])
                                ->when($cita, fn ($q) => $q->where('id', '!=', $cita->id))
                                ->lockForUpdate()
                                ->first();

            if ($choqueMedico) {
                throw new \DomainException("Este horario acaba de ser tomado por otra persona. Elige otro, por favor.");
            }

            // 3. Reprogramación: actualizamos la cita existente
            if ($cita) {
                $cita->update([
                    'medico_id' => $datos['medico_id'],
                    'fecha' => $datos['fecha'],
                    'hora_inicio' => $datos['hora_inicio'],
                    'hora_fin' => $datos['hora_fin'],
                ]);
                return $cita;
            }

            // 4. Creación normal
            return Cita::create([
                'paciente_id' => $datos['paciente_id'],
                'medico_id' => $datos['medico_id'],
                'fecha' => $datos['fecha'],
                'hora_inicio' => $datos['hora_inicio'],
                'hora_fin' => $datos['hora_fin'],
                'estado' => 'Pendiente',
            ]);
        });
    }
}
