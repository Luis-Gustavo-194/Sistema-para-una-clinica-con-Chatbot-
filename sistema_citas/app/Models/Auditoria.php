<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    // La tabla solo tiene 'fecha_hora' (la llena MySQL con CURRENT_TIMESTAMP)
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];

    // Relación: quién hizo la acción
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    // Registra una acción del usuario logueado: qué hizo, sobre qué registro y desde qué IP
    public static function registrar(string $accion, string $tabla, ?int $registroId = null): void
    {
        static::create([
            'usuario_id' => Auth::id(),
            'accion' => mb_substr($accion, 0, 100),
            'tabla_afectada' => $tabla,
            'registro_id' => $registroId,
            'direccion_ip' => request()->ip(),
        ]);
    }
}
