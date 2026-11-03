<?php

namespace App\Models;

use App\Support\DatosSensibles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    // La tabla solo tiene 'fecha_hora' (la llena MySQL con CURRENT_TIMESTAMP)
    public $timestamps = false;

    // Canales desde donde se puede hacer una acción
    const CANAL_WEB = 'web';

    const CANAL_CHATBOT = 'chatbot';

    // HU17: acciones de acceso al sistema (tabla_afectada = 'usuarios')
    const ACCESO_INICIO = 'Inició sesión';

    const ACCESO_CIERRE = 'Cerró sesión';

    const ACCESO_FALLIDO = 'Intento fallido de inicio de sesión';

    const ACCESO_CUENTA_BLOQUEADA = 'Intento de inicio de sesión con cuenta bloqueada';

    const ACCESO_USUARIO_INACTIVO = 'Intento de inicio de sesión con usuario desactivado';

    const ACCESO_BLOQUEO = 'Cuenta bloqueada por intentos fallidos';

    // Acciones que cuentan como fallos de acceso (se resaltan en el panel de auditoría)
    const FALLOS_DE_ACCESO = [self::ACCESO_FALLIDO, self::ACCESO_CUENTA_BLOQUEADA, self::ACCESO_USUARIO_INACTIVO, self::ACCESO_BLOQUEO];

    protected $guarded = [];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];

    // Relación: quién hizo la acción
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    // Filtro: intentos de acceso fallidos y bloqueos (el texto puede llevar el correo al final)
    public function scopeFallosDeAcceso($query)
    {
        return $query->where(function ($q) {
            foreach (self::FALLOS_DE_ACCESO as $accion) {
                $q->orWhere('accion', $accion)->orWhere('accion', 'like', "{$accion} (%");
            }
        });
    }

    public function esFalloDeAcceso(): bool
    {
        foreach (self::FALLOS_DE_ACCESO as $accion) {
            if ($this->accion === $accion || str_starts_with($this->accion, "{$accion} (")) {
                return true;
            }
        }

        return false;
    }

    // Registra una acción: qué se hizo, sobre qué registro, desde qué IP y por qué canal.
    // Por defecto el autor es el usuario logueado; el chatbot puede indicar el paciente identificado por DNI.
    // Los DNI, correos y teléfonos del texto se guardan enmascarados.
    public static function registrar(
        string $accion,
        string $tabla,
        ?int $registroId = null,
        string $canal = self::CANAL_WEB,
        ?int $usuarioId = null,
    ): void {
        static::create([
            'usuario_id' => $usuarioId ?? Auth::id(),
            'accion' => mb_substr(DatosSensibles::enmascarar($accion), 0, 100),
            'tabla_afectada' => $tabla,
            'registro_id' => $registroId,
            'direccion_ip' => request()->ip(),
            'canal' => $canal,
        ]);
    }
}
