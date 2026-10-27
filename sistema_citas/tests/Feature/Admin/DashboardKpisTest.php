<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Concerns\EsquemaClinica;
use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Usuario;

class DashboardKpisTest extends TestCase
{
    use EsquemaClinica;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->admin = Usuario::find($this->crearUsuario(Rol::ADMINISTRADOR, 'admin@x.com'));
        $this->actingAs($this->admin);

        // "Hoy" fijo a mitad de mes para que los cálculos sean predecibles
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));
    }

    public function test_kpis_y_graficos_con_citas_controladas(): void
    {
        $cardio = $this->crearEspecialidad('Cardiología');
        $pedia = $this->crearEspecialidad('Pediatría');
        $this->crearEspecialidad('Dermatología', 0);
        $drA = $this->crearMedico($cardio, 'a@x.com', 'Ramírez');
        $drB = $this->crearMedico($pedia, 'b@x.com', 'Salazar');
        $this->crearMedico($pedia, 'c@x.com', 'Quispe', 0); // inactivo

        $p1 = $this->crearPaciente('11111111', 'Uno', '2026-10-02 09:00:00');
        $p2 = $this->crearPaciente('22222222', 'Dos', '2026-09-20 09:00:00'); // de otro mes

        $this->crearCita($p1, $drA, '2026-10-15', '10:00:00', 'Pendiente');   // hoy
        $this->crearCita($p2, $drA, '2026-10-15', '11:30:00', 'Cancelada');   // hoy, cancelada
        $this->crearCita($p1, $drB, '2026-10-16', '10:00:00', 'Confirmada');  // +1 día
        $this->crearCita($p2, $drB, '2026-10-22', '10:00:00', null);          // +7 días (estado NULL = Pendiente)
        $this->crearCita($p1, $drA, '2026-10-23', '10:00:00', 'Pendiente');   // +8 días: fuera de "próximos 7"
        $this->crearCita($p1, $drA, '2026-10-10', '10:00:00', 'Atendida');
        $this->crearCita($p2, $drA, '2026-10-11', '10:00:00', 'Atendida');
        $this->crearCita($p1, $drB, '2026-10-12', '10:00:00', 'Atendida');
        $this->crearCita($p2, $drA, '2026-10-13', '10:00:00', 'No Asistio');
        $this->crearCita($p1, $drA, '2026-09-30', '10:00:00', 'Atendida');    // mes anterior

        $respuesta = $this->get('/admin/dashboard')->assertOk();
        $kpis = $respuesta->viewData('kpis');

        $this->assertSame(1, $kpis['citas_hoy']);
        $this->assertSame(2, $kpis['citas_proximos_7']);
        $this->assertSame(2, $kpis['medicos_activos']);
        $this->assertSame(3, $kpis['total_medicos']);
        $this->assertSame(2, $kpis['total_pacientes']);
        $this->assertSame(1, $kpis['pacientes_nuevos_mes']);
        $this->assertSame(8, $kpis['citas_mes']);            // 9 del mes - 1 cancelada
        $this->assertSame(11.1, $kpis['tasa_cancelacion']);  // 1 / 9
        $this->assertSame(25.0, $kpis['tasa_inasistencia']); // 1 / (3 atendidas + 1 no asistió)
        $this->assertSame(3, $kpis['atendidas_mes']);
        $this->assertSame(2, $kpis['especialidades_activas']);

        $graficos = $respuesta->viewData('graficos');
        $this->assertSame([3, 1, 3, 1, 1], $graficos['por_estado']['valores']); // Pendiente, Confirmada, Atendida, No Asistio, Cancelada
        $this->assertSame(['Cardiología' => 5, 'Pediatría' => 3], array_combine($graficos['por_especialidad']['etiquetas'], $graficos['por_especialidad']['valores']));

        $porDia = $graficos['por_dia'];
        $this->assertCount(37, $porDia['etiquetas']);       // 30 días hasta hoy + 7 próximos
        $this->assertSame('15/10', $porDia['etiquetas'][29]);
        $this->assertSame(1, $porDia['valores'][29]);       // hoy, sin la cancelada
        $this->assertFalse($porDia['futuro'][29]);
        $this->assertTrue($porDia['futuro'][30]);
        $this->assertSame('10/10', $porDia['etiquetas'][24]);
        $this->assertSame(1, $porDia['valores'][24]);

        $top = $respuesta->viewData('topMedicos')->values();
        $this->assertSame('Ramírez', $top[0]->apellidos);
        $this->assertSame(5, $top[0]->citas_mes);
        $this->assertSame(1, $top[0]->canceladas_mes);

        $respuesta->assertSee('11.1%')->assertSee('25.0%')
            ->assertSee('data-grafico="por-dia"', false)
            ->assertSee('data-grafico="por-estado"', false);
    }

    public function test_tasas_sin_datos_se_muestran_como_guion(): void
    {
        $respuesta = $this->get('/admin/dashboard')->assertOk();

        $this->assertNull($respuesta->viewData('kpis')['tasa_cancelacion']);
        $this->assertNull($respuesta->viewData('kpis')['tasa_inasistencia']);
        $respuesta->assertSee('No hay citas registradas este mes.');
    }

    public function test_muestra_la_actividad_reciente_de_auditoria(): void
    {
        $this->post('/admin/especialidades', ['nombre' => 'Neurología']);

        $actividad = $this->get('/admin/dashboard')->assertOk()->assertSee('Creó la especialidad Neurología')->viewData('actividad');
        $this->assertSame('admin@x.com', $actividad->first()->usuario->correo);
        $this->assertInstanceOf(Auditoria::class, $actividad->first());
    }
}
