<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// HU04: correo con el enlace del Password broker para crear una nueva contraseña.
// Se envía de inmediato (sin cola) para no depender de un worker.
class RestablecerContrasena extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'correo' => $notifiable->getEmailForPasswordReset(),
        ]);
        $minutos = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Restablece tu contraseña · '.config('clinica.nombre'))
            ->greeting('¡Hola!')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Crear nueva contraseña', $url)
            ->line("Este enlace vence en {$minutos} minutos y solo se puede usar una vez.")
            ->line('Si no solicitaste el cambio, ignora este correo: tu contraseña actual seguirá funcionando.')
            ->salutation('Atentamente, '.config('clinica.nombre'));
    }
}
