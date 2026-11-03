<?php

namespace Tests\Feature\Admin;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// Mantenimiento de datos por el Administrador (horarios de médicos y datos de la clínica)
// y su reflejo en el directorio público
class MantenimientoTest extends TestCase
{
    use EsquemaClinica;

    private Usuario $admin;

    private int $cardiologia;

    private int $medico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->admin = Usuario::find($this->crearUsuario(Rol::ADMINISTRADOR, 'admin@x.com'));
        $this->cardiologia = $this->crearEspecialidad('Cardiología');
        $this->medico = $this->crearMedico($this->cardiologia, 'doc@x.com', 'Ramírez');
    }

    private function datosClinica(array $cambios = []): array
    {
        return $cambios + [
            'nombre' => 'Clínica San Pablo',
            'eslogan' => 'Siempre contigo',
            'ciudad' => 'Cusco',
            'direccion' => 'Av. Sol 456, Cusco',
            'telefono_fijo' => '(084) 111 222',
            'telefono_celular' => '955 444 333',
            'correo' => 'contacto@sanpablo.pe',
            'horario' => 'Lunes a sábado de 7:00 a 21:00',
        ];
    }

    public function test_el_admin_edita_los_horarios_de_un_medico(): void
    {
        $this->actingAs($this->admin)->get("/admin/medicos/{$this->medico}/horarios")->assertOk()->assertSee('Dr(a). Doc Ramírez');

        $this->put("/admin/medicos/{$this->medico}/horarios", ['dias' => [
            1 => ['activo' => '1', 'inicio' => '08:00', 'fin' => '12:00', 'duracion' => '30', 'consultorio' => 'C-201'],
            3 => ['activo' => '1', 'inicio' => '14:00', 'fin' => '18:00', 'duracion' => '20', 'consultorio' => 'C-201'],
        ]])->assertRedirect("/admin/medicos/{$this->medico}/horarios")->assertSessionHas('exito');

        $this->assertSame([1, 3], DB::table('horarios_medicos')->where('medico_id', $this->medico)->orderBy('dia_semana')->pluck('dia_semana')->map(fn ($d) => (int) $d)->all());
        $this->assertDatabaseHas('auditorias', ['accion' => "Actualizó los horarios del médico #{$this->medico}", 'usuario_id' => $this->admin->id]);

        // El directorio público muestra el nuevo horario
        auth()->logout();
        $this->get('/directorio')->assertOk()->assertSee('Lun')->assertSee('08:00–12:00')->assertSee('Mié')->assertSee('14:00–18:00')->assertSee('C-201');
    }

    public function test_valida_los_horarios(): void
    {
        $this->actingAs($this->admin)->put("/admin/medicos/{$this->medico}/horarios", ['dias' => [
            2 => ['activo' => '1', 'inicio' => '12:00', 'fin' => '08:00', 'duracion' => '5'],
        ]])->assertSessionHasErrors(['dias.2.fin', 'dias.2.duracion']);

        $this->assertDatabaseCount('horarios_medicos', 0);
    }

    public function test_el_admin_edita_los_datos_de_la_clinica_y_se_ven_en_el_portal(): void
    {
        $this->actingAs($this->admin)->get('/admin/clinica')->assertOk()->assertSee(config('clinica.nombre'));

        $this->put('/admin/clinica', $this->datosClinica())->assertRedirect('/admin/clinica')->assertSessionHas('exito');

        $this->assertDatabaseHas('configuraciones', ['clave' => 'telefonos.fijo', 'valor' => '(084) 111 222']);
        $this->assertDatabaseHas('auditorias', ['accion' => 'Actualizó los datos de la clínica', 'tabla_afectada' => 'configuraciones']);

        auth()->logout();
        $this->get('/')->assertOk()->assertSee('Clínica San Pablo')->assertSee('(084) 111 222');
        $this->get('/directorio')->assertSee('Lunes a sábado de 7:00 a 21:00');
    }

    public function test_valida_los_datos_de_la_clinica(): void
    {
        $this->actingAs($this->admin)->put('/admin/clinica', $this->datosClinica(['nombre' => '', 'correo' => 'no-es-correo', 'telefono_fijo' => 'abc']))
            ->assertSessionHasErrors(['nombre', 'correo', 'telefono_fijo' => 'El formato de teléfono fijo no es válido.']);

        $this->assertDatabaseCount('configuraciones', 0);
    }

    public function test_solo_el_administrador_hace_mantenimiento(): void
    {
        $recepcionista = Usuario::find($this->crearUsuario(Rol::RECEPCIONISTA, 'recepcion@x.com'));
        $medico = Usuario::find(DB::table('medicos')->where('id', $this->medico)->value('usuario_id'));

        $this->actingAs($recepcionista)->put('/admin/clinica', $this->datosClinica())->assertRedirect('/login');
        $this->actingAs($medico)->put("/admin/medicos/{$this->medico}/horarios", ['dias' => []])->assertRedirect('/login');

        $this->assertDatabaseCount('configuraciones', 0);
    }

    public function test_directorio_filtra_y_oculta_inactivos(): void
    {
        $pediatria = $this->crearEspecialidad('Pediatría');
        $this->crearMedico($pediatria, 'ped@x.com', 'Torres');
        $this->crearMedico($this->cardiologia, 'inactivo@x.com', 'Inactivo', 0);
        $this->crearMedico($this->crearEspecialidad('Cerrada', 0), 'cerrada@x.com', 'Cerrado');

        $this->get('/directorio')->assertOk()
            ->assertSee('Doc Ramírez')->assertSee('Doc Torres')->assertSee('Horario por confirmar')
            ->assertDontSee('Doc Inactivo')->assertDontSee('Doc Cerrado');

        $this->get("/directorio?especialidad={$pediatria}")->assertSee('Doc Torres')->assertDontSee('Doc Ramírez');
        $this->get('/directorio?buscar=ramí')->assertSee('Doc Ramírez')->assertDontSee('Doc Torres');
    }
}
