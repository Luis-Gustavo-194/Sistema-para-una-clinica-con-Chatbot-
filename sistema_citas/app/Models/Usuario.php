<?php

namespace App\Models;

use App\Notifications\RestablecerContrasena; // Herramienta de seguridad de Laravel
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'rol_id',
        'correo',
        'contrasena',
        'estado',
        'intentos_fallidos',
        'bloqueado_hasta',
    ];

    // Por seguridad, Laravel ocultará la contraseña si consultamos este usuario
    protected $hidden = [
        'contrasena',
    ];

    protected $casts = [
        'bloqueado_hasta' => 'datetime',
    ];

    // Le decimos a Laravel que nuestra contraseña se llama 'contrasena' y no 'password'
    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    // La tabla usuarios de MySQL no tiene columna remember_token
    public function getRememberTokenName()
    {
        return '';
    }

    // El Password broker y las notificaciones usan 'correo' en vez de 'email'
    public function getEmailForPasswordReset()
    {
        return $this->correo;
    }

    public function routeNotificationForMail(): string
    {
        return $this->correo;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RestablecerContrasena($token));
    }

    // HU01: la cuenta está bloqueada mientras no pase la fecha de bloqueo
    public function estaBloqueado(): bool
    {
        return $this->bloqueado_hasta !== null && $this->bloqueado_hasta->isFuture();
    }

    // Suma un intento fallido; al llegar al máximo bloquea la cuenta y reinicia el contador.
    // Devuelve true si este intento provocó el bloqueo.
    public function registrarIntentoFallido(): bool
    {
        $intentos = (int) $this->intentos_fallidos + 1;

        if ($intentos >= config('auth.bloqueo.intentos')) {
            $this->forceFill([
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => now()->addMinutes(config('auth.bloqueo.minutos')),
            ])->save();

            return true;
        }

        $this->forceFill(['intentos_fallidos' => $intentos])->save();

        return false;
    }

    public function reiniciarIntentos(): void
    {
        if ((int) $this->intentos_fallidos !== 0 || $this->bloqueado_hasta !== null) {
            $this->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();
        }
    }

    // Panel al que se dirige el usuario según su rol
    public function rutaPanel(): string
    {
        return match ((int) $this->rol_id) {
            Rol::ADMINISTRADOR => '/admin/dashboard',
            Rol::MEDICO => '/dashboard-medico',
            Rol::PACIENTE => '/paciente',
            Rol::RECEPCIONISTA => '/recepcion/pacientes',
            default => '/',
        };
    }

    // Relación: Un usuario pertenece a un rol específico (¡La mantenemos!)
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    // Ficha de paciente asociada (si el usuario es o fue paciente)
    public function paciente()
    {
        return $this->hasOne(Paciente::class, 'usuario_id');
    }
}
