<?php

namespace App\Providers;

use App\Mail\GmailApiTransport;
use App\Models\Configuracion;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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
        // Correos por la API HTTPS de Gmail (Railway bloquea SMTP saliente)
        Mail::extend('gmail', fn () => new GmailApiTransport(config('services.gmail', [])));

        // Chatbot: 30 mensajes por minuto por conversación (sesión) y un tope amplio por IP,
        // para que varios pacientes detrás de la misma red no se bloqueen entre sí
        RateLimiter::for('chatbot', fn (Request $request) => [
            Limit::perMinute(30)->by('chatbot-sesion:'.$request->session()->getId()),
            Limit::perMinute(600)->by('chatbot-ip:'.$request->ip()),
        ]);

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
