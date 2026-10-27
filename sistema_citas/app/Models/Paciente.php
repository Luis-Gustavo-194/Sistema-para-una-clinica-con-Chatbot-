<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paciente extends Model
{
    protected $table = 'pacientes';

    protected $fillable = [
        'usuario_id',
        'dni',
        'nombres',
        'apellidos',
        'telefono',
        'fecha_nacimiento'
    ];

    // Relación: Un paciente es dueño de un usuario (credenciales)
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    // Relación: Un paciente puede tener muchas citas
    public function citas()
    {
        return $this->hasMany(Cita::class, 'paciente_id');
    }

    // Edad en años a partir de fecha_nacimiento (null si no está registrada)
    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento ? \Illuminate\Support\Carbon::parse($this->fecha_nacimiento)->age : null;
    }
}