<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\EsquemaClinica;
use App\Models\Rol;
use App\Models\Usuario;

class InicioTest extends TestCase
{
    use EsquemaClinica;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
    }

    public function test_portada_muestra_especialidades_y_medicos_activos(): void
    {
        $cardio = $this->crearEspecialidad('Cardiología');
        DB::table('especialidades')->where('id', $cardio)->update(['descripcion' => 'Cuidamos tu corazón']);
        $inactiva = $this->crearEspecialidad('Neurología', 0);
        $this->crearMedico($cardio, 'activo@x.com', 'Ramírez');
        $this->crearMedico($cardio, 'inactivo@x.com', 'Quispe', 0);
        $this->crearMedico($inactiva, 'neuro@x.com', 'Salazar');

        $this->get('/')->assertOk()
            ->assertSee(config('clinica.nombre'))
            ->assertSee('¿En qué podemos ayudarte hoy?')
            ->assertSee('Cardiología')
            ->assertSee('Cuidamos tu corazón')
            ->assertSee('1 médico')
            ->assertSee('Ramírez')
            ->assertDontSee('Quispe')     // médico inactivo
            ->assertDontSee('Neurología') // especialidad inactiva
            ->assertDontSee('Salazar')    // médico de especialidad inactiva
            ->assertSee('Reservar cita')
            ->assertSee('Iniciar sesión');
    }

    public function test_con_sesion_muestra_mi_panel(): void
    {
        $this->actingAs(Usuario::find($this->crearUsuario(Rol::ADMINISTRADOR, 'admin@x.com')))
            ->get('/')->assertOk()
            ->assertSee('Mi panel')
            ->assertSee('href="/admin/dashboard"', false);
    }

    public function test_el_nombre_de_la_clinica_es_configurable(): void
    {
        config(['clinica.nombre' => 'Centro Médico Prueba']);

        $this->get('/')->assertOk()->assertSee('Centro Médico Prueba');
        $this->get('/login')->assertOk()->assertSee('Centro Médico Prueba');
    }
}
