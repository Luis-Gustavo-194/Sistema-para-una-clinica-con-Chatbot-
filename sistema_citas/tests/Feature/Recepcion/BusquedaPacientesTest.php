<?php

namespace Tests\Feature\Recepcion;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// HU06: buscador de pacientes de recepción (parcial por DNI, nombres y apellidos)
class BusquedaPacientesTest extends TestCase
{
    use EsquemaClinica;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->actingAs(Usuario::find($this->crearUsuario(Rol::RECEPCIONISTA, 'recepcion@x.com')));

        DB::table('pacientes')->insert([
            ['dni' => '70000001', 'nombres' => 'Mario', 'apellidos' => 'Quispe Huamán'],
            ['dni' => '70000002', 'nombres' => 'Ana María', 'apellidos' => 'Gonzales Quispe'],
            ['dni' => '45871236', 'nombres' => 'Luis', 'apellidos' => 'Torres Paz'],
        ]);
    }

    public function test_busqueda_parcial_por_dni(): void
    {
        $this->get('/recepcion/pacientes?buscar=7000')
            ->assertOk()
            ->assertSee('Quispe Huamán')
            ->assertSee('Gonzales Quispe')
            ->assertDontSee('Torres Paz');

        // El DNI se busca por su inicio, no por cualquier parte
        $this->get('/recepcion/pacientes?buscar=0001')->assertSee('No se encontraron pacientes');
    }

    public function test_busqueda_parcial_por_nombres_y_apellidos(): void
    {
        // "Quis" inicia el primer apellido de Mario y el segundo de Ana María
        $this->get('/recepcion/pacientes?buscar=quis')->assertSee('Quispe Huamán')->assertSee('Gonzales Quispe')->assertDontSee('Torres Paz');

        // Varias palabras: todas deben coincidir
        $this->get('/recepcion/pacientes?buscar=Mar Quis')->assertSee('Quispe Huamán')->assertSee('Gonzales Quispe');
        $this->get('/recepcion/pacientes?buscar=Ana Gonz')->assertSee('Gonzales Quispe')->assertDontSee('Quispe Huamán');
        $this->get('/recepcion/pacientes?buscar=María')->assertSee('Gonzales Quispe')->assertDontSee('Torres Paz');
    }

    public function test_sin_resultados_ofrece_registrar(): void
    {
        $this->get('/recepcion/pacientes?buscar=Zapata')
            ->assertOk()
            ->assertSee('No se encontraron pacientes para «Zapata».')
            ->assertSee('Registrar paciente nuevo');

        // Los comodines de SQL se buscan como texto
        $this->get('/recepcion/pacientes?buscar=%25')->assertSee('No se encontraron pacientes');
    }

    public function test_lista_y_detalle(): void
    {
        $id = DB::table('pacientes')->where('dni', '45871236')->value('id');

        $this->get('/recepcion/pacientes')->assertOk()->assertSee('Torres Paz, Luis')->assertSee(route('recepcion.pacientes.show', $id));
        $this->get("/recepcion/pacientes/{$id}")->assertOk()->assertSee('Luis Torres Paz')->assertSee('45871236');
    }

    public function test_responde_en_menos_de_2_segundos_con_miles_de_pacientes(): void
    {
        $filas = [];
        for ($i = 0; $i < 3000; $i++) {
            $filas[] = ['dni' => (string) (10000000 + $i), 'nombres' => "Nombre{$i}", 'apellidos' => "Apellido{$i} Prueba"];
        }
        foreach (array_chunk($filas, 500) as $lote) {
            DB::table('pacientes')->insert($lote);
        }

        $inicio = microtime(true);
        $this->get('/recepcion/pacientes?buscar=Apellido2999')->assertOk()->assertSee('Apellido2999 Prueba');
        $segundos = microtime(true) - $inicio;

        $this->assertLessThan(2.0, $segundos, "La búsqueda tardó {$segundos} s");
    }
}
