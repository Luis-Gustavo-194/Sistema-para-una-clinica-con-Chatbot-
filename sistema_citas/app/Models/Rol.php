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

    protected $fillable = [
        'nombre'
    ];

    // Relación: Un rol puede tener muchos usuarios
    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'rol_id');
    }
}
