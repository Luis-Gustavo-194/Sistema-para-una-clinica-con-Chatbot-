<?php

namespace App\Providers;

use App\Models\Configuracion;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Datos de la clínica editados por el Administrador (tabla configuraciones) sobre config/clinica.php.
        // Si la BD no está disponible (p. ej. al instalar), se usan los valores de config/.env.
        try {
            foreach (Configuracion::valores() as $clave => $valor) {
                if (in_array($clave, config('clinica.editables', []), true) && $valor !== null && $valor !== '') {
                    config(["clinica.{$clave}" => $valor]);
                }
            }
        } catch (\Throwable) {
            // Sin conexión a la BD: se mantienen los valores por defecto
        }
    }
}
