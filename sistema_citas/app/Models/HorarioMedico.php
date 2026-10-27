<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorarioMedico extends Model
{
    protected $table = 'horarios_medicos';

    protected $guarded = [];

    // dia_semana: 1 = lunes ... 7 = domingo (igual que Carbon::dayOfWeekIso)
    const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public function medico()
    {
        return $this->belongsTo(Medico::class, 'medico_id');
    }
}
