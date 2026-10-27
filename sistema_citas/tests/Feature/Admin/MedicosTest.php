<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Concerns\EsquemaClinica;
use App\Models\Medico;
use App\Models\Rol;
use App\Models\Usuario;

class MedicosTest extends TestCase
{
    use EsquemaClinica;

    private Usuario $admin;
    private int $cardiologia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->admin = Usuario::find($this->crearUsuario(Rol::ADMINISTRADOR, 'admin@x.com'));
        $this->cardiologia = $this->crearEspecialidad('Cardiología');
        $this->actingAs($this->admin);
    }

    private function datosMedico(array $cambios = []): array
    {
        return $cambios + [
            'nombres' => 'Ana',
            'apellidos' => 'Torres',
            'colegiatura' => 'CMP-55555',
            'especialidad_id' => $this->cardiologia,
            'correo' => 'ana.torres@clinica.com',
            'contrasena' => 'Clave1234',
            'contrasena_confirmation' => 'Clave1234',
        ];
    }

    public function test_crear_medico_crea_su_usuario_de_acceso(): void
    {
        $this->get('/admin/medicos/create')->assertOk();

        $this->post('/admin/medicos', $this->datosMedico())->assertRedirect('/admin/medicos')->assertSessionHas('exito');

        $usuario = Usuario::where('correo', 'ana.torres@clinica.com')->first();
        $this->assertSame(Rol::MEDICO, (int) $usuario->rol_id);
        $this->assertSame(1, (int) $usuario->estado);
        $this->assertTrue(Hash::check('Clave1234', $usuario->contrasena));
        $this->assertDatabaseHas('medicos', ['usuario_id' => $usuario->id, 'colegiatura' => 'CMP-55555', 'especialidad_id' => $this->cardiologia]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'Creó al médico Ana Torres', 'tabla_afectada' => 'medicos']);

        // El nuevo médico puede iniciar sesión y entra a su panel
        auth()->logout();
        $this->post('/login', ['correo' => 'ana.torres@clinica.com', 'contrasena' => 'Clave1234'])->assertRedirect('/dashboard-medico');
    }

    public function test_validaciones_al_crear(): void
    {
        $this->crearMedico($this->cardiologia, 'usado@clinica.com');
        $colegiaturaUsada = DB::table('medicos')->value('colegiatura');
        $inactiva = $this->crearEspecialidad('Pediatría', 0);

        $this->post('/admin/medicos', $this->datosMedico(['correo' => 'usado@clinica.com']))->assertSessionHasErrors('correo');
        $this->post('/admin/medicos', $this->datosMedico(['colegiatura' => $colegiaturaUsada]))->assertSessionHasErrors('colegiatura');
        $this->post('/admin/medicos', $this->datosMedico(['especialidad_id' => $inactiva]))->assertSessionHasErrors('especialidad_id');
        $this->post('/admin/medicos', $this->datosMedico(['contrasena_confirmation' => 'otra']))
            ->assertSessionHasErrors(['contrasena' => 'La confirmación de contraseña no coincide.']);
        $this->post('/admin/medicos', $this->datosMedico(['contrasena' => '', 'contrasena_confirmation' => '']))->assertSessionHasErrors('contrasena');

        $this->assertSame(1, Medico::count());
    }

    public function test_editar_sin_contrasena_mantiene_la_actual(): void
    {
        $id = $this->crearMedico($this->cardiologia, 'doc@clinica.com');
        $medico = Medico::find($id);
        $hashAnterior = $medico->usuario->contrasena;

        $this->get("/admin/medicos/{$id}/edit")->assertOk()->assertSee('doc@clinica.com');
        $this->put("/admin/medicos/{$id}", $this->datosMedico([
            'colegiatura' => $medico->colegiatura, 'correo' => 'nuevo@clinica.com', 'contrasena' => '', 'contrasena_confirmation' => '',
        ]))->assertRedirect('/admin/medicos');

        $medico->refresh();
        $this->assertSame('Torres', $medico->apellidos);
        $this->assertSame('nuevo@clinica.com', $medico->usuario->correo);
        $this->assertSame($hashAnterior, $medico->usuario->contrasena);

        $this->put("/admin/medicos/{$id}", $this->datosMedico(['colegiatura' => $medico->colegiatura, 'correo' => 'nuevo@clinica.com']));
        $this->assertTrue(Hash::check('Clave1234', $medico->usuario->fresh()->contrasena));
    }

    public function test_al_editar_se_puede_conservar_una_especialidad_inactiva(): void
    {
        $inactiva = $this->crearEspecialidad('Pediatría', 0);
        $id = $this->crearMedico($inactiva, 'doc@clinica.com');
        $medico = Medico::find($id);

        $this->put("/admin/medicos/{$id}", $this->datosMedico([
            'especialidad_id' => $inactiva, 'colegiatura' => $medico->colegiatura, 'correo' => 'doc@clinica.com', 'contrasena' => '', 'contrasena_confirmation' => '',
        ]))->assertSessionHasNoErrors();
    }

    public function test_desactivar_bloquea_login_y_cierra_la_sesion_abierta(): void
    {
        $id = $this->crearMedico($this->cardiologia, 'doc@clinica.com');
        $usuarioMedico = Medico::find($id)->usuario;

        $this->patch("/admin/medicos/{$id}/estado")->assertSessionHas('exito');
        $this->assertSame(0, (int) $usuarioMedico->fresh()->estado);
        $this->assertDatabaseHas('auditorias', ['accion' => 'Desactivó al médico Doc Prueba']);

        // Sesión que ya estaba abierta: se cierra en la siguiente petición
        $this->actingAs($usuarioMedico->fresh())->get('/dashboard-medico')->assertRedirect('/login');
        $this->assertGuest();

        // Login nuevo: rechazado
        $this->post('/login', ['correo' => 'doc@clinica.com', 'contrasena' => 'secreto123'])->assertSessionHasErrors('correo');
        $this->assertGuest();

        // Reactivar
        $this->actingAs($this->admin)->patch("/admin/medicos/{$id}/estado");
        $this->assertSame(1, (int) $usuarioMedico->fresh()->estado);
    }

    public function test_no_elimina_medico_con_citas(): void
    {
        $id = $this->crearMedico($this->cardiologia, 'doc@clinica.com');
        $this->crearCita($this->crearPaciente('11111111'), $id, '2026-01-10', '10:00:00', 'Atendida');

        $this->delete("/admin/medicos/{$id}")->assertSessionHasErrors('medico');
        $this->assertDatabaseHas('medicos', ['id' => $id]);
    }

    public function test_elimina_medico_sin_citas_junto_con_su_usuario(): void
    {
        $id = $this->crearMedico($this->cardiologia, 'doc@clinica.com');
        $usuarioId = Medico::find($id)->usuario_id;

        $this->delete("/admin/medicos/{$id}")->assertRedirect('/admin/medicos');
        $this->assertDatabaseMissing('medicos', ['id' => $id]);
        $this->assertDatabaseMissing('usuarios', ['id' => $usuarioId]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'Eliminó al médico Doc Prueba', 'registro_id' => $id]);
    }

    public function test_listado_con_filtros(): void
    {
        $pediatria = $this->crearEspecialidad('Pediatría');
        $this->crearMedico($this->cardiologia, 'a@clinica.com', 'Ramírez');
        $this->crearMedico($pediatria, 'b@clinica.com', 'Salazar');
        $this->crearMedico($pediatria, 'c@clinica.com', 'Quispe', 0);

        $this->get('/admin/medicos')->assertOk()->assertSee('Ramírez')->assertSee('Salazar')->assertSee('Quispe');
        $this->get('/admin/medicos?buscar=salaz')->assertSee('Salazar')->assertDontSee('Ramírez');
        $this->get("/admin/medicos?especialidad={$pediatria}")->assertSee('Salazar')->assertSee('Quispe')->assertDontSee('Ramírez');
        $this->get('/admin/medicos?estado=0')->assertSee('Quispe')->assertDontSee('Salazar');
    }

    public function test_listado_paginado_de_10_en_10(): void
    {
        foreach (range(1, 12) as $n) {
            $this->crearMedico($this->cardiologia, "doc{$n}@clinica.com", sprintf('Apellido%02d', $n));
        }

        $this->get('/admin/medicos')->assertOk()->assertSee('Apellido10')->assertDontSee('Apellido11')->assertSee('aria-label="Paginación"', false);
        $this->get('/admin/medicos?page=2')->assertOk()->assertSee('Apellido12')->assertDontSee('Apellido01');
    }

    public function test_solo_el_administrador_tiene_acceso(): void
    {
        $medico = Usuario::find($this->crearUsuario(Rol::MEDICO, 'medico@x.com'));

        $this->actingAs($medico)->get('/admin/medicos')->assertRedirect('/login');
        $this->actingAs($medico)->post('/admin/medicos', $this->datosMedico())->assertRedirect('/login');
        $this->assertDatabaseMissing('usuarios', ['correo' => 'ana.torres@clinica.com']);
    }
}
