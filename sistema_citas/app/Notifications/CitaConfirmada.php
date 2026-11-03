<?php

namespace App\Notifications;

use App\Models\Cita;
use App\Services\DisponibilidadService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Correo opcional (config clinica.correo_confirmacion) al agendar o reprogramar una cita.
// Se envía de inmediato (sin cola) para no depender de un worker.
class CitaConfirmada extends Notification
{
    public function __construct(public Cita $cita, public bool $reprogramada = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cita = $this->cita;
        $fecha = ucfirst($cita->inicio()->translatedFormat('l d/m/Y'));
        $hora = $cita->inicio()->format('g:i A');
        $consultorio = DisponibilidadService::consultorio($cita->medico, $cita->inicio()->toDateString());
        $horas = config('clinica.cancelacion_horas_minimas');

        $correo = (new MailMessage)
            ->subject(($this->reprogramada ? 'Cita reprogramada' : 'Cita confirmada').' · '.config('clinica.nombre'))
            ->greeting('¡Hola!')
            ->line($this->reprogramada ? 'Tu cita fue reprogramada. Estos son los nuevos datos:' : 'Tu cita quedó registrada con estos datos:')
            ->line("**Fecha:** {$fecha} a las {$hora}")
            ->line("**Médico:** Dr(a). {$cita->medico->nombres} {$cita->medico->apellidos} ({$cita->medico->especialidad->nombre})");

        if ($consultorio) {
            $correo->line("**Consultorio:** {$consultorio}");
        }

        return $correo
            ->action('Ver mis citas', route('paciente.citas.index'))
            ->line("Si no puedes asistir, cancélala con al menos {$horas} horas de anticipación desde tu panel o el asistente virtual.")
            ->salutation('Atentamente, '.config('clinica.nombre'));
    }
}
