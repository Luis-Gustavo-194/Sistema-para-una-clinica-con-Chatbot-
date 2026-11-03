<?php

namespace Tests\Feature\Admin;

use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\RestablecerContrasena;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// HU03: gestión de usuarios y asignación de rol, solo para el Administrador
class UsuariosRolTest extends TestCase
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

    private function pacienteConFicha(string $correo, string $dni): Usuario
    {
        $usuario = Usuario::find($this->crearUsuario(Rol::PACIENTE, $correo));
        DB::table('pacientes')->where('id', $this->crearPaciente($dni))->update(['usuario_id' => $usuario->id]);

        return $usuario;
    }

    public function test_lista_usuarios_con_filtro_por_rol(): void
    {
        $this->pacienteConFicha('rosa@correo.com', '11111111');
        $this->crearUsuario(Rol::RECEPCIONISTA, 'recepcion@x.com');

        $this->get('/admin/usuarios')->assertOk()->assertSee('rosa@correo.com')->assertSee('recepcion@x.com');
        $this->get('/admin/usuarios?rol='.Rol::RECEPCIONISTA)->assertOk()->assertSee('recepcion@x.com')->assertDontSee('rosa@correo.com');
    }

    public function test_asignar_rol_recepcionista_y_auditar(): void
    {
        $usuario = $this->pacienteConFicha('rosa@correo.com', '11111111');

        $this->patch("/admin/usuarios/{$usuario->id}/rol", ['rol_id' => Rol::RECEPCIONISTA])->assertSessionHas('exito');

        $this->assertSame(Rol::RECEPCIONISTA, (int) $usuario->fresh()->rol_id);
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $this->admin->id,
            'accion' => "Cambió el rol del usuario #{$usuario->id}: Paciente → Recepcionista",
            'tabla_afectada' => 'usuarios',
        ]);

        // Ya puede entrar a recepción
        $this->actingAs($usuario->fresh())->get('/recepcion/pacientes')->assertOk();
    }

    public function test_no_puede_cambiar_su_propio_rol(): void
    {
        $this->patch("/admin/usuarios/{$this->admin->id}/rol", ['rol_id' => Rol::RECEPCIONISTA])
            ->assertSessionHasErrors(['usuario' => 'No puedes cambiar tu propio rol.']);

        $this->assertSame(Rol::ADMINISTRADOR, (int) $this->admin->fresh()->rol_id);
    }

    public function test_el_rol_medico_no_se_asigna_ni_se_quita_desde_aqui(): void
    {
        $medico = Usuario::find(DB::table('medicos')->where('id', $this->crearMedico($this->crearEspecialidad('Cardiología'), 'doc@x.com'))->value('usuario_id'));
        $usuario = $this->pacienteConFicha('rosa@correo.com', '11111111');

        $this->patch("/admin/usuarios/{$medico->id}/rol", ['rol_id' => Rol::ADMINISTRADOR])
            ->assertSessionHasErrors(['usuario' => 'El rol de los médicos se gestiona desde el módulo de médicos.']);
        $this->patch("/admin/usuarios/{$usuario->id}/rol", ['rol_id' => Rol::MEDICO])->assertSessionHasErrors('rol_id');

        $this->assertSame(Rol::MEDICO, (int) $medico->fresh()->rol_id);
        $this->assertSame(Rol::PACIENTE, (int) $usuario->fresh()->rol_id);
    }

    public function test_rol_paciente_solo_si_tiene_ficha(): void
    {
        $recepcionista = Usuario::find($this->crearUsuario(Rol::RECEPCIONISTA, 'recepcion@x.com'));

        $this->patch("/admin/usuarios/{$recepcionista->id}/rol", ['rol_id' => Rol::PACIENTE])
            ->assertSessionHasErrors(['usuario' => 'recepcion@x.com no tiene ficha de paciente; no se le puede asignar ese rol.']);

        $this->assertSame(Rol::RECEPCIONISTA, (int) $recepcionista->fresh()->rol_id);
    }

    public function test_crear_cuenta_de_personal_envia_enlace_para_la_contrasena(): void
    {
        Notification::fake();

        $this->post('/admin/usuarios', ['correo' => 'nueva@clinica.com', 'rol_id' => Rol::RECEPCIONISTA])->assertSessionHas('exito');

        $usuario = Usuario::where('correo', 'nueva@clinica.com')->first();
        $this->assertSame(Rol::RECEPCIONISTA, (int) $usuario->rol_id);
        Notification::assertSentTo($usuario, RestablecerContrasena::class);
        $this->post('/admin/usuarios', ['correo' => 'otra@clinica.com', 'rol_id' => Rol::MEDICO])->assertSessionHasErrors('rol_id');
    }

    public function test_solo_el_administrador_gestiona_usuarios(): void
    {
        $recepcionista = Usuario::find($this->crearUsuario(Rol::RECEPCIONISTA, 'recepcion@x.com'));
        $usuario = $this->pacienteConFicha('rosa@correo.com', '11111111');

        $this->actingAs($recepcionista)
            ->patch("/admin/usuarios/{$usuario->id}/rol", ['rol_id' => Rol::ADMINISTRADOR])
            ->assertRedirect('/login');

        $this->assertSame(Rol::PACIENTE, (int) $usuario->fresh()->rol_id);
    }
}
