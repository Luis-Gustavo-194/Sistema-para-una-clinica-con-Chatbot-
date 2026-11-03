<?php

namespace Tests\Feature\Recepcion;

use App\Models\Paciente;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\RestablecerContrasena;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// HU05: recepción registra y edita la ficha del paciente (rutas web)
class PacientesTest extends TestCase
{
    use EsquemaClinica;

    private Usuario $recepcionista;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->recepcionista = Usuario::find($this->crearUsuario(Rol::RECEPCIONISTA, 'recepcion@x.com'));
        $this->actingAs($this->recepcionista);
        Notification::fake();
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'dni' => '76543210',
            'nombres' => 'Rosa',
            'apellidos' => 'Quispe Mamani',
            'telefono' => '999888777',
            'fecha_nacimiento' => '1995-04-12',
            'seguro' => 'EsSalud',
            'correo' => 'rosa@correo.com',
            'consentimiento' => '1',
        ];
    }

    public function test_registrar_paciente_crea_su_cuenta_y_envia_el_enlace(): void
    {
        $this->get('/recepcion/pacientes/create')->assertOk()->assertSee('Ley N.° 29733');

        $respuesta = $this->post('/recepcion/pacientes', $this->datos());

        $paciente = Paciente::where('dni', '76543210')->first();
        $respuesta->assertRedirect("/recepcion/pacientes/{$paciente->id}")->assertSessionHas('exito');
        $this->assertSame('EsSalud', $paciente->seguro);
        $this->assertNotNull($paciente->consentimiento_datos_at);
        $this->assertSame(Rol::PACIENTE, (int) $paciente->usuario->rol_id);
        $this->assertSame('rosa@correo.com', $paciente->usuario->correo);
        Notification::assertSentTo($paciente->usuario, RestablecerContrasena::class);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $this->recepcionista->id,
            'accion' => "Registró al paciente #{$paciente->id} desde recepción",
            'tabla_afectada' => 'pacientes',
            'registro_id' => $paciente->id,
        ]);
    }

    public function test_validaciones_obligatorias(): void
    {
        $this->post('/recepcion/pacientes', [])->assertSessionHasErrors(['dni', 'nombres', 'apellidos', 'fecha_nacimiento', 'seguro', 'correo', 'consentimiento']);

        $this->post('/recepcion/pacientes', $this->datos(['dni' => '1234']))->assertSessionHasErrors(['dni' => 'El campo DNI debe tener 8 dígitos.']);
        $this->post('/recepcion/pacientes', $this->datos(['telefono' => 'abc']))->assertSessionHasErrors(['telefono' => 'El formato de teléfono no es válido.']);
        $this->post('/recepcion/pacientes', $this->datos(['seguro' => 'Otro']))->assertSessionHasErrors('seguro');
        $this->post('/recepcion/pacientes', $this->datos(['fecha_nacimiento' => today()->toDateString()]))->assertSessionHasErrors('fecha_nacimiento');
        $this->post('/recepcion/pacientes', $this->datos(['consentimiento' => null]))
            ->assertSessionHasErrors(['consentimiento' => 'Debes aceptar el tratamiento de tus datos personales (Ley N.° 29733).']);

        $this->assertSame(0, Paciente::count());
        $this->assertSame(1, Usuario::count());
    }

    public function test_dni_y_correo_duplicados(): void
    {
        $this->crearPaciente('76543210');
        $this->crearUsuario(Rol::PACIENTE, 'rosa@correo.com');

        $this->post('/recepcion/pacientes', $this->datos())->assertSessionHasErrors([
            'dni' => 'El valor ingresado en DNI ya está registrado.',
            'correo' => 'El valor ingresado en correo ya está registrado.',
        ]);

        $this->assertSame(1, Paciente::count());
    }

    public function test_editar_ficha_audita_los_campos_que_cambiaron(): void
    {
        $this->post('/recepcion/pacientes', $this->datos());
        $paciente = Paciente::where('dni', '76543210')->first();

        $this->get("/recepcion/pacientes/{$paciente->id}/edit")->assertOk()->assertSee('value="76543210"', false);
        $this->put("/recepcion/pacientes/{$paciente->id}", $this->datos(['telefono' => '988777666', 'seguro' => 'SIS', 'consentimiento' => null]))
            ->assertRedirect("/recepcion/pacientes/{$paciente->id}");

        $paciente->refresh();
        $this->assertSame('988777666', $paciente->telefono);
        $this->assertSame('SIS', $paciente->seguro);
        $this->assertDatabaseHas('auditorias', ['accion' => "Editó la ficha del paciente #{$paciente->id} (telefono, seguro)", 'registro_id' => $paciente->id]);
    }

    public function test_editar_conserva_su_propio_dni_y_correo_pero_no_los_de_otro(): void
    {
        $this->post('/recepcion/pacientes', $this->datos());
        $this->post('/recepcion/pacientes', $this->datos(['dni' => '11111111', 'correo' => 'otro@correo.com']));
        $paciente = Paciente::where('dni', '76543210')->first();

        $this->put("/recepcion/pacientes/{$paciente->id}", $this->datos(['nombres' => 'Rosa María']))->assertSessionHasNoErrors();
        $this->put("/recepcion/pacientes/{$paciente->id}", $this->datos(['dni' => '11111111', 'correo' => 'otro@correo.com']))
            ->assertSessionHasErrors(['dni', 'correo']);

        $this->assertSame('Rosa María', $paciente->fresh()->nombres);
        $this->assertSame('76543210', $paciente->fresh()->dni);
    }

    public function test_ficha_muestra_datos_y_citas(): void
    {
        $this->post('/recepcion/pacientes', $this->datos());
        $paciente = Paciente::where('dni', '76543210')->first();
        $medico = $this->crearMedico($this->crearEspecialidad('Cardiología'), 'doc@x.com', 'Torres');
        $this->crearCita($paciente->id, $medico, '2026-10-16');

        $this->get("/recepcion/pacientes/{$paciente->id}")
            ->assertOk()
            ->assertSee('Rosa Quispe Mamani')
            ->assertSee('EsSalud')
            ->assertSee('16/10/2026')
            ->assertSee('Torres');
    }

    public function test_el_administrador_tambien_tiene_acceso(): void
    {
        $this->actingAs(Usuario::find($this->crearUsuario(Rol::ADMINISTRADOR, 'admin@x.com')));

        $this->post('/recepcion/pacientes', $this->datos())->assertSessionHasNoErrors();

        $this->assertSame(1, Paciente::count());
    }

    public function test_paciente_y_medico_no_pueden_registrar_pacientes(): void
    {
        $paciente = Usuario::find($this->crearUsuario(Rol::PACIENTE, 'p@x.com'));
        $medico = Usuario::find(DB::table('medicos')->where('id', $this->crearMedico($this->crearEspecialidad('Pediatría'), 'm@x.com'))->value('usuario_id'));

        $this->actingAs($paciente)->post('/recepcion/pacientes', $this->datos())->assertRedirect('/login');
        $this->actingAs($medico)->post('/recepcion/pacientes', $this->datos(['dni' => '22222222', 'correo' => 'x@correo.com']))->assertRedirect('/login');

        $this->assertSame(0, Paciente::count());
    }
}
