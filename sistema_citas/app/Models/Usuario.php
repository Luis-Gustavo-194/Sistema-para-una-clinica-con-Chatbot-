<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable; // Herramienta de seguridad de Laravel

class Usuario extends Authenticatable
{
    protected $table = 'usuarios';

    protected $fillable = [
        'rol_id',
        'correo',
        'contrasena',
        'estado'
    ];

    // Por seguridad, Laravel ocultará la contraseña si consultamos este usuario
    protected $hidden = [
        'contrasena',
    ];

    // Le decimos a Laravel que nuestra contraseña se llama 'contrasena' y no 'password'
    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    // Panel al que se dirige el usuario según su rol
    public function rutaPanel(): string
    {
        return match ((int) $this->rol_id) {
            Rol::ADMINISTRADOR => '/admin/dashboard',
            Rol::MEDICO => '/dashboard-medico',
            Rol::PACIENTE => '/paciente',
            default => '/',
        };
    }

    // Relación: Un usuario pertenece a un rol específico (¡La mantenemos!)
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }
}
