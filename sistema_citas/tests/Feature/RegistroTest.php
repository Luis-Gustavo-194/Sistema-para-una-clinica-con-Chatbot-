<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Concerns\EsquemaClinica;
use App\Models\Paciente;
use App\Models\Rol;
use App\Models\Usuario;

class RegistroTest extends TestCase
{
    use EsquemaClinica;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'dni' => '76543210',
            'nombres' => 'Rosa',
            'apellidos' => 'Quispe',
            'telefono' => '999888777',
            'fecha_nacimiento' => '1995-04-12',
            'correo' => 'rosa@correo.com',
            'contrasena' => 'MiClave123',
            'contrasena_confirmation' => 'MiClave123',
        ];
    }

    public function test_registro_crea_usuario_paciente_e_inicia_sesion(): void
    {
        $this->get('/registro')->assertOk()->assertSee('Crear cuenta de paciente');

        $this->post('/registro', $this->datos())->assertRedirect('/paciente')->assertSessionHas('exito');

        $usuario = Usuario::where('correo', 'rosa@correo.com')->first();
        $this->assertSame(Rol::PACIENTE, (int) $usuario->rol_id);
        $this->assertTrue(Hash::check('MiClave123', $usuario->contrasena));
        $this->assertDatabaseHas('pacientes', ['usuario_id' => $usuario->id, 'dni' => '76543210', 'nombres' => 'Rosa']);
        $this->assertAuthenticatedAs($usuario);
        $this->assertDatabaseHas('auditorias', ['usuario_id' => $usuario->id, 'tabla_afectada' => 'pacientes']);

        $this->get('/paciente')->assertOk()->assertSee('Hola, Rosa');
    }

    public function test_dni_y_correo_duplicados(): void
    {
        $this->crearPaciente('76543210');
        $this->crearUsuario(Rol::PACIENTE, 'rosa@correo.com');

        $this->post('/registro', $this->datos())->assertSessionHasErrors(['dni', 'correo']);
        $this->assertSame(1, Paciente::count());
        $this->assertGuest();
    }

    public function test_validaciones_de_formato(): void
    {
        $this->post('/registro', $this->datos(['dni' => '1234']))->assertSessionHasErrors(['dni' => 'El campo DNI debe tener 8 dígitos.']);
        $this->post('/registro', $this->datos(['dni' => 'abcdefgh']))->assertSessionHasErrors('dni');
        $this->post('/registro', $this->datos(['fecha_nacimiento' => today()->addDay()->toDateString()]))->assertSessionHasErrors('fecha_nacimiento');
        $this->post('/registro', $this->datos(['contrasena_confirmation' => 'otra']))->assertSessionHasErrors('contrasena');
        $this->assertSame(0, Usuario::count());
    }

    public function test_usuario_con_sesion_va_a_su_panel(): void
    {
        $this->actingAs(Usuario::find($this->crearUsuario(Rol::ADMINISTRADOR, 'admin@x.com')))
            ->get('/registro')->assertRedirect('/admin/dashboard');
    }
}
