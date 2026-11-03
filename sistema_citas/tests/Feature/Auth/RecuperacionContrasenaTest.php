<?php

namespace Tests\Feature\Auth;

use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\RestablecerContrasena;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// HU04: recuperación de contraseña con el Password broker (token por correo, 60 minutos, un solo uso)
class RecuperacionContrasenaTest extends TestCase
{
    use EsquemaClinica;

    private const MENSAJE_GENERICO = 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña. Revisa tu bandeja de entrada.';

    private const MENSAJE_TOKEN_INVALIDO = 'El enlace para restablecer la contraseña no es válido o ya venció. Solicita uno nuevo.';

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->usuario = Usuario::find($this->crearUsuario(Rol::PACIENTE, 'rosa@correo.com'));
        Notification::fake();
    }

    private function restablecer(string $token, string $contrasena = 'NuevaClave1')
    {
        return $this->post('/restablecer-contrasena', [
            'token' => $token,
            'correo' => 'rosa@correo.com',
            'contrasena' => $contrasena,
            'contrasena_confirmation' => $contrasena,
        ]);
    }

    public function test_solicitar_enlace_envia_el_correo_con_el_enlace(): void
    {
        $this->get('/login')->assertSee(route('password.request'));
        $this->get('/olvide-contrasena')->assertOk()->assertSee('¿Olvidaste tu contraseña?');

        $this->post('/olvide-contrasena', ['correo' => 'rosa@correo.com'])->assertSessionHas('exito', self::MENSAJE_GENERICO);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'rosa@correo.com']);
        Notification::assertSentTo($this->usuario, RestablecerContrasena::class, function (RestablecerContrasena $notificacion) {
            $correo = $notificacion->toMail($this->usuario);

            return $correo->actionUrl === route('password.reset', ['token' => $notificacion->token, 'correo' => 'rosa@correo.com'])
                && $correo->subject === 'Restablece tu contraseña · '.config('clinica.nombre');
        });
    }

    public function test_correo_no_registrado_recibe_la_misma_respuesta(): void
    {
        $this->post('/olvide-contrasena', ['correo' => 'nadie@correo.com'])->assertSessionHas('exito', self::MENSAJE_GENERICO);

        Notification::assertNothingSent();
    }

    public function test_token_valido_cambia_la_contrasena_y_quita_el_bloqueo(): void
    {
        $this->usuario->forceFill(['intentos_fallidos' => 3, 'bloqueado_hasta' => now()->addMinutes(10)])->save();
        $token = Password::createToken($this->usuario);

        $this->get("/restablecer-contrasena/{$token}?correo=rosa@correo.com")->assertOk()->assertSee('value="rosa@correo.com"', false);
        $this->restablecer($token)->assertRedirect('/login')->assertSessionHas('exito');

        $this->usuario->refresh();
        $this->assertTrue(Hash::check('NuevaClave1', $this->usuario->contrasena));
        $this->assertNull($this->usuario->bloqueado_hasta);
        $this->assertSame(0, (int) $this->usuario->intentos_fallidos);
        $this->assertDatabaseHas('auditorias', ['usuario_id' => $this->usuario->id, 'accion' => 'Restableció su contraseña por correo']);
        $this->post('/login', ['correo' => 'rosa@correo.com', 'contrasena' => 'NuevaClave1'])->assertRedirect('/paciente');
    }

    public function test_token_expirado_no_sirve(): void
    {
        $token = Password::createToken($this->usuario);
        $this->travel(61)->minutes();

        $this->restablecer($token)->assertSessionHasErrors(['correo' => self::MENSAJE_TOKEN_INVALIDO]);

        $this->assertTrue(Hash::check('secreto123', $this->usuario->fresh()->contrasena));
    }

    public function test_token_reutilizado_no_sirve(): void
    {
        $token = Password::createToken($this->usuario);
        $this->restablecer($token)->assertRedirect('/login');
        app('auth')->forgetGuards();

        $this->restablecer($token, 'OtraClave22')->assertSessionHasErrors(['correo' => self::MENSAJE_TOKEN_INVALIDO]);

        $this->assertTrue(Hash::check('NuevaClave1', $this->usuario->fresh()->contrasena));
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_token_invalido_no_sirve(): void
    {
        Password::createToken($this->usuario);

        $this->restablecer('token-inventado')->assertSessionHasErrors(['correo' => self::MENSAJE_TOKEN_INVALIDO]);

        $this->assertTrue(Hash::check('secreto123', $this->usuario->fresh()->contrasena));
    }

    public function test_token_de_otra_cuenta_no_sirve(): void
    {
        $otro = Usuario::find($this->crearUsuario(Rol::PACIENTE, 'otro@correo.com'));
        $tokenAjeno = Password::createToken($otro);

        $this->restablecer($tokenAjeno)->assertSessionHasErrors(['correo' => self::MENSAJE_TOKEN_INVALIDO]);
    }

    public function test_valida_la_nueva_contrasena(): void
    {
        $token = Password::createToken($this->usuario);

        $this->post('/restablecer-contrasena', [
            'token' => $token,
            'correo' => 'rosa@correo.com',
            'contrasena' => 'corta',
            'contrasena_confirmation' => 'otra',
        ])->assertSessionHasErrors(['contrasena']);

        $this->assertSame(1, DB::table('password_reset_tokens')->count());
    }
}
