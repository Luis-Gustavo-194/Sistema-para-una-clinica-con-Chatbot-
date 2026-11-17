<?php

namespace App\Chatbot\Ejecutores;

use App\Chatbot\Conversacion;
use App\Chatbot\Entidades;
use App\Chatbot\Respuesta;
use App\Models\Cita;
use App\Models\Paciente;

// Paso común de cancelar y reprogramar: identificar al paciente y elegir una de sus citas vigentes
trait EligeCitaVigente
{
    use IdentificaPaciente;

    abstract protected function accion(): string; // 'cancelar' o 'reprogramar'

    abstract protected function pedirDni(): string;

    abstract protected function citaElegida(Conversacion $conversacion, Cita $cita): Respuesta;

    public function iniciar(Conversacion $conversacion, Entidades $entidades): Respuesta
    {
        $conversacion->iniciar(static::FLUJO, 'dni');

        if ($paciente = $this->pacienteLogueado()) {
            return $this->mostrarCitasVigentes($conversacion, $paciente);
        }

        return $entidades->dni ? $this->identificar($conversacion, $entidades->dni) : new Respuesta($this->pedirDni());
    }

    public function continuar(Conversacion $conversacion, string $mensaje, Entidades $entidades): Respuesta
    {
        if ($conversacion->paso() === 'dni') {
            return $this->identificar($conversacion, $entidades->dni);
        }

        $cita = $this->citaSeleccionada($conversacion, $mensaje);
        if (! $cita) {
            return new Respuesta("Selecciona una de las citas mostradas, o escribe 'salir' para detener el proceso.", $conversacion->dato('opciones', []));
        }

        return $this->citaElegida($conversacion, $cita);
    }

    // "Atrás" o "cambiar dni": se vuelve a empezar la búsqueda de citas
    public function corregir(Conversacion $conversacion, ?string $campo): Respuesta
    {
        return $this->iniciar($conversacion, new Entidades);
    }

    private function identificar(Conversacion $conversacion, ?string $dni): Respuesta
    {
        $resultado = $this->buscarPaciente($dni);

        return $resultado instanceof Respuesta ? $resultado : $this->mostrarCitasVigentes($conversacion, $resultado);
    }

    // Muestra las citas que el paciente aún puede cancelar o reprogramar
    private function mostrarCitasVigentes(Conversacion $conversacion, Paciente $paciente): Respuesta
    {
        $accion = $this->accion();
        $citas = Cita::modificables()
            ->where('paciente_id', $paciente->id)
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        if ($citas->isEmpty()) {
            $conversacion->terminar();

            return new Respuesta("No tienes citas pendientes para {$accion}.");
        }

        // Para cancelar se respeta la anticipación mínima: las citas muy próximas no se ofrecen
        if ($accion === 'cancelar') {
            $citas = $citas->filter(fn (Cita $cita) => $cita->puedeCancelarse())->values();

            if ($citas->isEmpty()) {
                $conversacion->terminar();
                $horas = config('clinica.cancelacion_horas_minimas');

                return new Respuesta("Tus citas pendientes son dentro de menos de {$horas} horas y ya no se pueden cancelar por este medio. Comunícate con recepción al ".config('clinica.telefonos.fijo').'.');
            }
        }

        $etiqueta = $accion === 'cancelar' ? '❌ Cancelar' : '🔄 Reprogramar';
        $opciones = $citas->map(fn (Cita $cita) => [
            'texto' => "{$etiqueta} cita del ".date('d/m/Y', strtotime($cita->fecha)).' a las '.date('h:i A', strtotime($cita->hora_inicio)),
            'valor' => (string) $cita->id,
        ])->all();

        $conversacion->irA('seleccion');
        $conversacion->guardar([
            'paciente_id' => $paciente->id,
            'citas_ofrecidas' => array_column($opciones, 'valor'),
            'opciones' => $opciones,
        ]);

        $texto = $accion === 'cancelar'
            ? 'Encontré estos turnos a tu nombre. Haz clic en la cita que deseas cancelar:'
            : 'Encontré estos turnos a tu nombre. Selecciona la cita que deseas reprogramar:';

        return new Respuesta($texto, $opciones);
    }

    // Solo acepta una cita que se le mostró a este mismo paciente en el paso anterior
    private function citaSeleccionada(Conversacion $conversacion, string $mensaje): ?Cita
    {
        if (! in_array($mensaje, $conversacion->dato('citas_ofrecidas', []), true)) {
            return null;
        }

        return Cita::modificables()
            ->whereKey($mensaje)
            ->where('paciente_id', $conversacion->dato('paciente_id'))
            ->first();
    }
}
