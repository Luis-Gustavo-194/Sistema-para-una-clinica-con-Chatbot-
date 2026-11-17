<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

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
            'seguro' => 'SIS',
            'consentimiento' => '1',
        ];
    }

    public function test_registro_crea_usuario_paciente_e_inicia_sesion(): void
    {
        $this->get('/registro')->assertOk()->assertSee('Crear cuenta de paciente');

        $this->post('/registro', $this->datos())->assertRedirect('/paciente')->assertSessionHas('exito');

        $usuario = Usuario::where('correo', 'rosa@correo.com')->first();
        $this->assertSame(Rol::PACIENTE, (int) $usuario->rol_id);
        $this->assertTrue(Hash::check('MiClave123', $usuario->contrasena));
        $this->assertDatabaseHas('pacientes', ['usuario_id' => $usuario->id, 'dni' => '76543210', 'nombres' => 'Rosa', 'seguro' => 'SIS']);
        $this->assertNotNull(Paciente::where('dni', '76543210')->value('consentimiento_datos_at'));
        $this->assertAuthenticatedAs($usuario);
        // HU02 en la bitácora, con el DNI enmascarado (HU18)
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $usuario->id,
            'accion' => 'Se registró como paciente (DNI 76****10) y aceptó el tratamiento de datos',
            'tabla_afectada' => 'pacientes',
            'canal' => 'web',
        ]);

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

    public function test_sin_consentimiento_de_datos_no_se_registra(): void
    {
        $this->post('/registro', $this->datos(['consentimiento' => null]))
            ->assertSessionHasErrors(['consentimiento' => 'Debes aceptar el tratamiento de tus datos personales (Ley N.° 29733).']);

        $this->assertSame(0, Usuario::count());
        $this->assertGuest();
    }

    public function test_seguro_debe_ser_de_la_lista(): void
    {
        $this->post('/registro', $this->datos(['seguro' => 'Otro']))->assertSessionHasErrors('seguro');
        $this->post('/registro', $this->datos(['seguro' => null]))->assertSessionHasErrors(['seguro' => 'El campo seguro es obligatorio.']);

        $this->assertSame(0, Usuario::count());
    }

    public function test_formulario_tiene_validacion_en_linea_y_consentimiento(): void
    {
        $this->get('/registro')
            ->assertOk()
            ->assertSee('x-data="validacionEnLinea"', false)
            ->assertSee('data-coincide="contrasena"', false)
            ->assertSee('Ley N.° 29733')
            ->assertSee(route('politica-privacidad'));

        // Google exige que la política explique el uso de la API de Gmail para publicar la app
        $this->get('/politica-de-privacidad')->assertOk()
            ->assertSee('Ley N.° 29733')
            ->assertSee('API de Gmail')
            ->assertSee('gmail.send');
    }

    public function test_usuario_con_sesion_va_a_su_panel(): void
    {
        $this->actingAs(Usuario::find($this->crearUsuario(Rol::ADMINISTRADOR, 'admin@x.com')))
            ->get('/registro')->assertRedirect('/admin/dashboard');
    }
}
