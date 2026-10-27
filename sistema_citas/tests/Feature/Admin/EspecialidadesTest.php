<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Tests\Concerns\EsquemaClinica;
use App\Models\Rol;
use App\Models\Usuario;

class EspecialidadesTest extends TestCase
{
    use EsquemaClinica;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->admin = Usuario::find($this->crearUsuario(Rol::ADMINISTRADOR, 'admin@x.com'));
        $this->actingAs($this->admin);
    }

    public function test_lista_y_busca_especialidades(): void
    {
        $this->crearEspecialidad('Cardiología');
        $this->crearEspecialidad('Pediatría', 0);

        $this->get('/admin/especialidades')->assertOk()->assertSee('Cardiología')->assertSee('Pediatría')->assertSee('Inactiva');
        $this->get('/admin/especialidades?buscar=pedia')->assertOk()->assertSee('Pediatría')->assertDontSee('Cardiología');
    }

    public function test_crear_especialidad_y_auditar(): void
    {
        $this->get('/admin/especialidades/create')->assertOk();

        $this->post('/admin/especialidades', ['nombre' => 'Neurología', 'descripcion' => 'Sistema nervioso'])
            ->assertRedirect('/admin/especialidades')
            ->assertSessionHas('exito');

        $this->assertDatabaseHas('especialidades', ['nombre' => 'Neurología', 'descripcion' => 'Sistema nervioso', 'estado' => 1]);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $this->admin->id,
            'accion' => 'Creó la especialidad Neurología',
            'tabla_afectada' => 'especialidades',
        ]);
    }

    public function test_valida_nombre_obligatorio_y_unico_en_espanol(): void
    {
        $this->crearEspecialidad('Cardiología');

        $this->post('/admin/especialidades', ['nombre' => ''])->assertSessionHasErrors(['nombre' => 'El campo nombre es obligatorio.']);
        $this->post('/admin/especialidades', ['nombre' => 'Cardiología'])->assertSessionHasErrors(['nombre' => 'El valor ingresado en nombre ya está registrado.']);
        $this->assertDatabaseCount('especialidades', 1);
    }

    public function test_editar_especialidad_conservando_su_nombre(): void
    {
        $id = $this->crearEspecialidad('Cardiología');

        $this->get("/admin/especialidades/{$id}/edit")->assertOk()->assertSee('Cardiología');
        $this->put("/admin/especialidades/{$id}", ['nombre' => 'Cardiología', 'descripcion' => 'Corazón'])
            ->assertRedirect('/admin/especialidades');

        $this->assertDatabaseHas('especialidades', ['id' => $id, 'descripcion' => 'Corazón']);
    }

    public function test_desactivar_y_activar(): void
    {
        $id = $this->crearEspecialidad('Cardiología');

        $this->patch("/admin/especialidades/{$id}/estado")->assertSessionHas('exito');
        $this->assertDatabaseHas('especialidades', ['id' => $id, 'estado' => 0]);

        $this->patch("/admin/especialidades/{$id}/estado");
        $this->assertDatabaseHas('especialidades', ['id' => $id, 'estado' => 1]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'Desactivó la especialidad Cardiología']);
    }

    public function test_no_elimina_si_tiene_medicos(): void
    {
        $id = $this->crearEspecialidad('Cardiología');
        $this->crearMedico($id, 'doc@x.com');

        $this->delete("/admin/especialidades/{$id}")->assertSessionHasErrors('especialidad');
        $this->assertDatabaseHas('especialidades', ['id' => $id]);
    }

    public function test_elimina_si_no_tiene_medicos(): void
    {
        $id = $this->crearEspecialidad('Cardiología');

        $this->delete("/admin/especialidades/{$id}")->assertRedirect('/admin/especialidades');
        $this->assertDatabaseMissing('especialidades', ['id' => $id]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'Eliminó la especialidad Cardiología', 'registro_id' => $id]);
    }

    public function test_solo_el_administrador_tiene_acceso(): void
    {
        $medico = Usuario::find($this->crearUsuario(Rol::MEDICO, 'medico@x.com'));
        $paciente = Usuario::find($this->crearUsuario(Rol::PACIENTE, 'paciente@x.com'));

        foreach ([$medico, $paciente] as $usuario) {
            $this->actingAs($usuario)->get('/admin/especialidades')->assertRedirect('/login');
            $this->actingAs($usuario)->post('/admin/especialidades', ['nombre' => 'Intrusa'])->assertRedirect('/login');
        }
        $this->assertDatabaseMissing('especialidades', ['nombre' => 'Intrusa']);
    }
}
