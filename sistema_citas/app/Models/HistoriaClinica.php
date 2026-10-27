<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoriaClinica extends Model
{
    protected $table = 'historias_clinicas';

    protected $fillable = [
        'cita_id',
        'diagnostico',
        'tratamiento',
        'receta_medica',
        'observaciones'
    ];

    // Relación: cada historia clínica corresponde a una cita atendida (cita_id es único)
    public function cita()
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }
}
