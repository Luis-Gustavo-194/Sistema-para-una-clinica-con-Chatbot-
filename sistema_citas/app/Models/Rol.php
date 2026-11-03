<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';

    // IDs fijos de la tabla roles en MySQL
    const PACIENTE = 1;

    const MEDICO = 2;

    const ADMINISTRADOR = 3;

    const RECEPCIONISTA = 4;

    // Nombre visible de cada rol (con tilde, a diferencia de la tabla)
    const NOMBRES = [
        self::PACIENTE => 'Paciente',
        self::MEDICO => 'Médico',
        self::ADMINISTRADOR => 'Administrador',
        self::RECEPCIONISTA => 'Recepcionista',
    ];

    protected $fillable = [
        'nombre',
    ];

    // Relación: Un rol puede tener muchos usuarios
    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'rol_id');
    }
}
