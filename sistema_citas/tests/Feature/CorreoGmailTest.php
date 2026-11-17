<?php

namespace Tests\Feature;

use App\Mail\GmailApiTransport;
use App\Models\Rol;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// Los correos salen por la API HTTPS de Gmail (Railway bloquea SMTP); si Gmail falla, quedan en el log.
// Google se simula con Http::fake: ninguna prueba hace peticiones reales.
class CorreoGmailTest extends TestCase
{
    use EsquemaClinica;

    private string $logCorreos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->crearUsuario(Rol::PACIENTE, 'rosa@correo.com');
        $this->crearUsuario(Rol::PACIENTE, 'luis@correo.com');

        // Un archivo por prueba: en Windows no se puede reabrir un log que se borró mientras seguía abierto
        $this->logCorreos = storage_path('logs/correos-prueba-'.uniqid().'.log');

        config([
            'mail.default' => 'gmail',
            'mail.from.address' => 'clinica@gmail.com',
            'services.gmail' => ['client_id' => 'id-prueba', 'client_secret' => 'secreto-prueba', 'refresh_token' => 'refresh-prueba'],
            'logging.channels.correos' => ['driver' => 'single', 'path' => $this->logCorreos],
            'mail.mailers.log.channel' => 'correos',
        ]);

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Log::forgetChannel('correos');
        File::delete($this->logCorreos);
        parent::tearDown();
    }

    private function simularGoogle(int $codigoEnvio = 200): void
    {
        Http::fake([
            GmailApiTransport::URL_TOKEN => Http::response(['access_token' => 'token-prueba', 'expires_in' => 3599]),
            GmailApiTransport::URL_ENVIO => Http::response($codigoEnvio === 200 ? ['id' => 'abc'] : ['error' => ['code' => $codigoEnvio]], $codigoEnvio),
        ]);

        Mail::purge('gmail');
        Mail::purge('failover');
    }

    private function pedirEnlace(string $correo = 'rosa@correo.com')
    {
        return $this->post('/olvide-contrasena', ['correo' => $correo])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('exito');
    }

    private function contarPeticiones(string $url): int
    {
        return Http::recorded(fn (Request $peticion) => $peticion->url() === $url)->count();
    }

    public function test_el_mailer_failover_usa_gmail_y_luego_el_log(): void
    {
        $this->assertSame(['gmail', 'log'], config('mail.mailers.failover.mailers'));
    }

    public function test_el_enlace_de_recuperacion_sale_por_la_api_de_gmail(): void
    {
        $this->simularGoogle();

        $this->pedirEnlace();

        Http::assertSent(fn (Request $peticion) => $peticion->url() === GmailApiTransport::URL_TOKEN
            && $peticion['refresh_token'] === 'refresh-prueba'
            && $peticion['client_id'] === 'id-prueba'
            && $peticion['grant_type'] === 'refresh_token');

        Http::assertSent(function (Request $peticion) {
            if ($peticion->url() !== GmailApiTransport::URL_ENVIO) {
                return false;
            }

            $mensaje = quoted_printable_decode(base64_decode(strtr($peticion['raw'], '-_', '+/')));

            return $peticion->hasHeader('Authorization', 'Bearer token-prueba')
                && str_contains($mensaje, 'To: rosa@correo.com')
                && str_contains($mensaje, '<clinica@gmail.com>')
                && str_contains($mensaje, '/restablecer-contrasena/');
        });
    }

    public function test_reutiliza_el_token_de_acceso_entre_correos(): void
    {
        $this->simularGoogle();

        $this->pedirEnlace('rosa@correo.com');
        $this->pedirEnlace('luis@correo.com');

        $this->assertSame(1, $this->contarPeticiones(GmailApiTransport::URL_TOKEN));
        $this->assertSame(2, $this->contarPeticiones(GmailApiTransport::URL_ENVIO));
    }

    public function test_si_gmail_falla_el_correo_va_al_log_y_la_pagina_responde(): void
    {
        config(['mail.default' => 'failover']);
        $this->simularGoogle(500);

        $this->pedirEnlace();

        $this->assertSame(1, $this->contarPeticiones(GmailApiTransport::URL_ENVIO));
        $this->assertStringContainsString('rosa@correo.com', File::get($this->logCorreos));
        $this->assertStringContainsString('/restablecer-contrasena/', File::get($this->logCorreos));
    }

    public function test_un_401_descarta_el_token_guardado(): void
    {
        config(['mail.default' => 'failover']);
        $this->simularGoogle(401);

        $this->pedirEnlace('rosa@correo.com');
        // Otra petición HTTP: el failover marca a Gmail como caído 60 s dentro del mismo proceso, así que se reconstruye
        Mail::purge('failover');
        $this->pedirEnlace('luis@correo.com');

        // Tras cada 401 se pide un token nuevo en lugar de reutilizar el rechazado
        $this->assertSame(2, $this->contarPeticiones(GmailApiTransport::URL_TOKEN));
        $this->assertStringContainsString('luis@correo.com', File::get($this->logCorreos));
    }

    public function test_sin_credenciales_la_pagina_no_falla_y_el_correo_va_al_log(): void
    {
        config(['mail.default' => 'failover', 'services.gmail' => ['client_id' => null, 'client_secret' => null, 'refresh_token' => null]]);
        $this->simularGoogle();

        $this->pedirEnlace();

        Http::assertNothingSent();
        $this->assertStringContainsString('rosa@correo.com', File::get($this->logCorreos));
    }
}
