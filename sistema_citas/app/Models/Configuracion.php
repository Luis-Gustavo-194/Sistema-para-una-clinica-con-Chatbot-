<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

// Datos de la clínica editados por el Administrador (clave → valor).
// AppServiceProvider los mezcla sobre config('clinica.*'), así el portal y los correos no cambian.
class Configuracion extends Model
{
    protected $table = 'configuraciones';

    const CACHE = 'clinica.configuracion';

    protected $fillable = ['clave', 'valor'];

    // ['nombre' => '...', 'telefonos.fijo' => '...'] guardados en la BD (vacío si la tabla aún no existe)
    public static function valores(): array
    {
        return Cache::rememberForever(self::CACHE, function () {
            if (! Schema::hasTable('configuraciones')) {
                return [];
            }

            return static::pluck('valor', 'clave')->all();
        });
    }

    public static function guardar(array $valores): void
    {
        foreach ($valores as $clave => $valor) {
            static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);

            // Se aplica también a la petición en curso (la siguiente ya lo toma de la caché/BD)
            if ($valor !== null && $valor !== '') {
                config(["clinica.{$clave}" => $valor]);
            }
        }

        Cache::forget(self::CACHE);
    }
}
