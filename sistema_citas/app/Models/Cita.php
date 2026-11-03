<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Cita extends Model
{
    // 1. Le decimos el nombre exacto de la tabla en MySQL
    protected $table = 'citas';

    // 2. Le decimos qué campos se pueden llenar desde el sistema web
    protected $fillable = [
        'paciente_id',
        'medico_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
        'canal',
    ];

    // Canales de origen de una reserva
    const CANALES = ['web' => 'Web', 'chatbot' => 'Chatbot', 'recepcion' => 'Recepción'];

    // Estados de la tabla (enum en MySQL) y su texto visible
    const ESTADOS = ['Pendiente' => 'Pendiente', 'Confirmada' => 'Confirmada', 'Atendida' => 'Atendida', 'Cancelada' => 'Cancelada', 'No Asistio' => 'No asistió'];

    // 3. Relación: Una cita pertenece a un paciente
    public function paciente()
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    // 4. Relación: Una cita pertenece a un médico
    public function medico()
    {
        return $this->belongsTo(Medico::class, 'medico_id');
    }

    // Relación: la historia clínica registrada al atender la cita
    public function historiaClinica()
    {
        return $this->hasOne(HistoriaClinica::class, 'cita_id');
    }

    // Estado mostrado al usuario (NULL cuenta como Pendiente; "No Asistio" con tilde)
    public function getEstadoTextoAttribute(): string
    {
        $estado = $this->estado ?? 'Pendiente';

        return $estado === 'No Asistio' ? 'No asistió' : $estado;
    }

    // Misma regla que scopeModificables, para una cita ya cargada
    public function esModificable(): bool
    {
        return $this->fecha >= today()->toDateString()
            && in_array($this->estado ?? 'Pendiente', ['Pendiente', 'Confirmada'], true);
    }

    // Fecha y hora de inicio de la cita
    public function inicio(): Carbon
    {
        return Carbon::parse($this->fecha)->setTimeFromTimeString($this->hora_inicio);
    }

    // El paciente puede cancelar si la cita es modificable y faltan al menos las horas mínimas configuradas
    public function puedeCancelarse(): bool
    {
        return $this->esModificable()
            && now()->diffInMinutes($this->inicio(), false) >= config('clinica.cancelacion_horas_minimas') * 60;
    }

    // Filtro: citas que el paciente aún puede cancelar o reprogramar (de hoy en adelante, Pendiente o Confirmada)
    public function scopeModificables($query)
    {
        $estado = $query->qualifyColumn('estado');

        return $query->where($query->qualifyColumn('fecha'), '>=', today()->toDateString())
            ->where(fn ($q) => $q->whereIn($estado, ['Pendiente', 'Confirmada'])->orWhereNull($estado));
    }

    // 5. Filtro: citas que no han sido canceladas (incluye registros antiguos sin estado)
    public function scopeNoCanceladas($query)
    {
        // Columna calificada (citas.estado) para que funcione en consultas con JOIN a otras tablas con 'estado'
        $estado = $query->qualifyColumn('estado');

        return $query->where(fn ($q) => $q->where($estado, '!=', 'Cancelada')->orWhereNull($estado));
    }
}
