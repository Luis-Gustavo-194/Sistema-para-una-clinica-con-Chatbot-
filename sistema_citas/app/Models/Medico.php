<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medico extends Model
{
    protected $table = 'medicos';

    protected $fillable = [
        'usuario_id',
        'especialidad_id',
        'colegiatura',
        'nombres',
        'apellidos'
    ];

    // Relación: Un médico es dueño de un usuario (para iniciar sesión)
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    // Relación: Un médico tiene muchas citas asignadas
    public function citas()
    {
        return $this->hasMany(Cita::class, 'medico_id');
    }

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }

    // Relación: bloques de atención semanales (horarios_medicos)
    public function horarios()
    {
        return $this->hasMany(HorarioMedico::class, 'medico_id');
    }

    // Filtro: médicos cuyo usuario está activo (los inactivos no se ofrecen en el chatbot)
    public function scopeActivos($query)
    {
        return $query->whereHas('usuario', fn ($q) => $q->where('estado', 1));
    }
}
