<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\Medico;
use App\Models\Paciente;
use App\Notifications\CitaConfirmada;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

// Único punto de reserva para la web del paciente, el chatbot, recepción y la API (HU12).
class CitaService
{
    // Intentos de la transacción: Laravel solo repite si hubo un interbloqueo (deadlock)
    const INTENTOS = 3;

    // Crea una cita nueva o, si se pasa $cita, la reprograma.
    // En ambos casos valida choques de horario dentro de una transacción.
    // Lanza \DomainException con un mensaje legible si el horario no está disponible.
    public static function guardar(array $datos, ?Cita $cita = null, string $canal = 'web'): Cita
    {
        try {
            $resultado = DB::transaction(function () use ($datos, $cita, $canal) {
                // 1. Se bloquean las filas del paciente y del médico (siempre en ese orden):
                //    las reservas del mismo médico quedan en fila y no se interbloquean
                //    como ocurre al bloquear solo rangos vacíos de citas.
                Paciente::whereKey($datos['paciente_id'])->lockForUpdate()->first();
                Medico::whereKey($datos['medico_id'])->lockForUpdate()->first();

                // 2. El paciente no puede tener dos citas que se crucen en horario
                $choquePaciente = Cita::noCanceladas()
                    ->where('paciente_id', $datos['paciente_id'])
                    ->where('fecha', $datos['fecha'])
                    ->where('hora_inicio', '<', $datos['hora_fin'])
                    ->where('hora_fin', '>', $datos['hora_inicio'])
                    ->when($cita, fn ($q) => $q->where('id', '!=', $cita->id))
                    ->exists();

                if ($choquePaciente) {
                    throw new \DomainException('El paciente ya tiene una cita reservada en ese mismo horario.');
                }

                // 3. El médico no puede tener otra cita que se cruce (aunque no empiece a la misma hora)
                $choqueMedico = Cita::noCanceladas()
                    ->where('medico_id', $datos['medico_id'])
                    ->where('fecha', $datos['fecha'])
                    ->where('hora_inicio', '<', $datos['hora_fin'])
                    ->where('hora_fin', '>', $datos['hora_inicio'])
                    ->when($cita, fn ($q) => $q->where('id', '!=', $cita->id))
                    ->exists();

                if ($choqueMedico) {
                    throw new \DomainException('Este horario acaba de ser tomado por otra persona. Elige otro, por favor.');
                }

                // 4. Reprogramación: actualizamos la cita existente
                if ($cita) {
                    $cita->update([
                        'medico_id' => $datos['medico_id'],
                        'fecha' => $datos['fecha'],
                        'hora_inicio' => $datos['hora_inicio'],
                        'hora_fin' => $datos['hora_fin'],
                    ]);

                    return $cita;
                }

                // 5. Creación normal
                return Cita::create([
                    'paciente_id' => $datos['paciente_id'],
                    'medico_id' => $datos['medico_id'],
                    'fecha' => $datos['fecha'],
                    'hora_inicio' => $datos['hora_inicio'],
                    'hora_fin' => $datos['hora_fin'],
                    'estado' => 'Pendiente',
                    'canal' => $canal,
                ]);
            }, self::INTENTOS);
        } catch (UniqueConstraintViolationException) {
            // Último resguardo: el índice único citas_turno_unico impidió la doble reserva
            throw new \DomainException('Este horario acaba de ser tomado por otra persona. Elige otro, por favor.');
        } catch (QueryException $e) {
            if (self::esInterbloqueo($e)) {
                throw new \DomainException('El sistema está atendiendo muchas reservas a la vez. Intenta de nuevo en unos segundos.');
            }
            throw $e;
        }

        self::enviarConfirmacion($resultado, $cita !== null);

        return $resultado;
    }

    // Cancela la cita y libera su turno (turno_activo pasa a NULL).
    // Sin $forzar exige la anticipación mínima; recepción puede forzar.
    public static function cancelar(Cita $cita, bool $forzar = false): void
    {
        if (! $cita->esModificable()) {
            throw new \DomainException('Esta cita ya no se puede cancelar.');
        }

        if (! $forzar && ! $cita->puedeCancelarse()) {
            $horas = config('clinica.cancelacion_horas_minimas');
            throw new \DomainException("Las citas solo se pueden cancelar con al menos {$horas} horas de anticipación. Comunícate con recepción.");
        }

        $cita->update(['estado' => 'Cancelada']);
    }

    // Correo opcional al paciente con los datos de la cita. Si el envío falla, la reserva sigue en pie.
    private static function enviarConfirmacion(Cita $cita, bool $reprogramada): void
    {
        if (! config('clinica.correo_confirmacion')) {
            return;
        }

        $usuario = $cita->paciente?->usuario;
        if (! $usuario) {
            return;
        }

        try {
            $usuario->notify(new CitaConfirmada($cita->loadMissing('medico.especialidad'), $reprogramada));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function esInterbloqueo(QueryException $e): bool
    {
        return in_array($e->getCode(), ['40001', '1213'], false)
            || str_contains($e->getMessage(), 'Deadlock')
            || str_contains($e->getMessage(), 'Lock wait timeout');
    }
}
