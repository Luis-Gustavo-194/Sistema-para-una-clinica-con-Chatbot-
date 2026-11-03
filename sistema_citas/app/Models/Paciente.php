<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Paciente extends Model
{
    protected $table = 'pacientes';

    // Valores permitidos para el campo seguro
    const SEGUROS = ['SIS', 'EsSalud', 'Privado', 'Ninguno'];

    protected $fillable = [
        'usuario_id',
        'dni',
        'nombres',
        'apellidos',
        'telefono',
        'fecha_nacimiento',
        'seguro',
        'consentimiento_datos_at',
    ];

    protected $casts = [
        'consentimiento_datos_at' => 'datetime',
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
        return $this->fecha_nacimiento ? Carbon::parse($this->fecha_nacimiento)->age : null;
    }

    // HU06: búsqueda parcial. Solo dígitos → DNI que empieza así (usa el índice único de dni);
    // texto → cada palabra debe iniciar alguno de los nombres o apellidos (p. ej. el segundo apellido).
    public function scopeBuscar(Builder $query, string $texto): Builder
    {
        $texto = trim($texto);

        if ($texto === '') {
            return $query;
        }

        if (ctype_digit($texto)) {
            return $query->where('dni', 'like', "{$texto}%");
        }

        foreach (preg_split('/\s+/', $texto) as $palabra) {
            $palabra = addcslashes($palabra, '%_\\');
            $query->where(fn ($q) => $q
                ->where('nombres', 'like', "{$palabra}%")
                ->orWhere('apellidos', 'like', "{$palabra}%")
                ->orWhere('nombres', 'like', "% {$palabra}%")
                ->orWhere('apellidos', 'like', "% {$palabra}%"));
        }

        return $query;
    }
}
