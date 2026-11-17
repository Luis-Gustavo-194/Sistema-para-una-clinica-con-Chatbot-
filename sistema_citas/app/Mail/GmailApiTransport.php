<?php

namespace App\Mail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

// Envía los correos por la API HTTPS de Gmail con la cuenta que autorizó el token (Railway bloquea SMTP saliente).
// Toda falla se lanza como TransportException para que el mailer failover deje el correo en el log.
class GmailApiTransport extends AbstractTransport
{
    const URL_TOKEN = 'https://oauth2.googleapis.com/token';

    const URL_ENVIO = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    const CLAVE_CACHE = 'gmail_api_token';

    public function __construct(private array $config)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (blank($this->config['client_id'] ?? null) || blank($this->config['client_secret'] ?? null) || blank($this->config['refresh_token'] ?? null)) {
            Log::warning('Faltan las credenciales GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET o GMAIL_REFRESH_TOKEN: los correos no se pueden enviar por Gmail.');

            throw new TransportException('Faltan las credenciales de la API de Gmail.');
        }

        try {
            $respuesta = Http::withToken($this->tokenDeAcceso())
                ->timeout(10)
                ->post(self::URL_ENVIO, ['raw' => rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '=')]);
        } catch (TransportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TransportException('No se pudo conectar con la API de Gmail: '.$e->getMessage(), 0, $e);
        }

        if ($respuesta->status() === 401) {
            // El token pudo vencer antes de tiempo: el próximo envío pide uno nuevo
            Cache::forget(self::CLAVE_CACHE);
        }

        if ($respuesta->failed()) {
            throw new TransportException("La API de Gmail rechazó el correo ({$respuesta->status()}): {$respuesta->body()}");
        }
    }

    // Los tokens de acceso de Google duran 60 minutos; se guardan 50 para no pedir uno por cada correo
    private function tokenDeAcceso(): string
    {
        return Cache::remember(self::CLAVE_CACHE, now()->addMinutes(50), function () {
            $respuesta = Http::asForm()->timeout(10)->post(self::URL_TOKEN, [
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'refresh_token' => $this->config['refresh_token'],
                'grant_type' => 'refresh_token',
            ]);

            if ($respuesta->failed() || blank($respuesta->json('access_token'))) {
                throw new TransportException("Google no entregó el token de acceso de Gmail ({$respuesta->status()}): {$respuesta->body()}");
            }

            return $respuesta->json('access_token');
        });
    }

    public function __toString(): string
    {
        return 'gmail+api://default';
    }
}
