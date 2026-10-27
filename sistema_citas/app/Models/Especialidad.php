<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Especialidad extends Model
{
    // Le indicamos el nombre exacto de tu tabla en la base de datos
    protected $table = 'especialidades';

    // Permitimos que se guarden datos masivamente
    protected $guarded = [];

    // Relación: una especialidad tiene muchos médicos
    public function medicos()
    {
        return $this->hasMany(Medico::class, 'especialidad_id');
    }
}
