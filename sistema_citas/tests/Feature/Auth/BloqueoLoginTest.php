<?php

namespace Tests\Feature\Auth;

use App\Models\Rol;
use App\Models\Usuario;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// HU01: bloqueo temporal de la cuenta tras 5 intentos fallidos (15 minutos)
class BloqueoLoginTest extends TestCase
{
    use EsquemaClinica;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->usuario = Usuario::find($this->crearUsuario(Rol::PACIENTE, 'rosa@correo.com'));
    }

    private function intentar(string $contrasena)
    {
        app('auth')->forgetGuards();

        return $this->post('/login', ['correo' => 'rosa@correo.com', 'contrasena' => $contrasena]);
    }

    private function fallar(int $veces): void
    {
        for ($i = 0; $i < $veces; $i++) {
            $this->intentar('incorrecta');
        }
    }

    public function test_cuatro_fallos_no_bloquean_y_cuentan_los_intentos(): void
    {
        $this->fallar(4);

        $this->usuario->refresh();
        $this->assertSame(4, (int) $this->usuario->intentos_fallidos);
        $this->assertNull($this->usuario->bloqueado_hasta);
        $this->intentar('incorrecta')->assertSessionHasErrors(['correo' => 'Tu cuenta está bloqueada temporalmente por varios intentos fallidos. Intenta de nuevo en 15 minuto(s) o restablece tu contraseña.']);
    }

    public function test_al_quinto_fallo_se_bloquea_y_rechaza_la_contrasena_correcta(): void
    {
        $this->freezeSecond();
        $this->fallar(5);

        $this->usuario->refresh();
        $this->assertTrue($this->usuario->bloqueado_hasta->equalTo(now()->addMinutes(15)));
        $this->assertSame(0, (int) $this->usuario->intentos_fallidos);
        $this->assertDatabaseHas('auditorias', ['usuario_id' => $this->usuario->id, 'accion' => 'Cuenta bloqueada por intentos fallidos']);

        $this->travel(14)->minutes();
        $this->intentar('secreto123')->assertSessionHasErrors(['correo' => 'Tu cuenta está bloqueada temporalmente por varios intentos fallidos. Intenta de nuevo en 1 minuto(s) o restablece tu contraseña.']);
        $this->assertGuest();
    }

    public function test_pasado_el_bloqueo_puede_entrar_y_se_reinicia(): void
    {
        $this->fallar(5);
        $this->travel(16)->minutes();

        $this->intentar('secreto123')->assertRedirect('/paciente');

        $this->assertAuthenticatedAs($this->usuario);
        $this->usuario->refresh();
        $this->assertNull($this->usuario->bloqueado_hasta);
    }

    public function test_un_login_correcto_reinicia_el_contador(): void
    {
        $this->fallar(3);
        $this->intentar('secreto123')->assertRedirect('/paciente');
        $this->get('/logout');

        $this->fallar(4);

        $this->assertSame(4, (int) $this->usuario->fresh()->intentos_fallidos);
        $this->assertNull($this->usuario->fresh()->bloqueado_hasta);
    }

    public function test_correo_inexistente_recibe_el_mensaje_generico(): void
    {
        $this->post('/login', ['correo' => 'nadie@correo.com', 'contrasena' => 'x'])
            ->assertSessionHasErrors(['correo' => 'Las credenciales proporcionadas no son correctas.']);
    }
}
