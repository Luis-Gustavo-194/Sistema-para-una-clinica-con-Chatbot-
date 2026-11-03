<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Services\CitaService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// HU12: el servicio de reserva que comparten la web, el chatbot y recepción
class CitaServiceTest extends TestCase
{
    use EsquemaClinica;

    private int $medico;

    private int $paciente;

    private int $otroPaciente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaClinica();
        $this->travelTo(Carbon::parse('2026-10-15 09:00:00'));
        $this->medico = $this->crearMedico($this->crearEspecialidad('Cardiología'), 'doc@x.com');
        $this->paciente = $this->crearPaciente('11111111', 'Juan');
        $this->otroPaciente = $this->crearPaciente('22222222', 'María');
    }

    private function datos(int $paciente, string $fecha = '2026-10-20', string $inicio = '10:00:00', string $fin = '10:30:00'): array
    {
        return ['paciente_id' => $paciente, 'medico_id' => $this->medico, 'fecha' => $fecha, 'hora_inicio' => $inicio, 'hora_fin' => $fin];
    }

    public function test_guarda_la_cita_con_su_canal(): void
    {
        $cita = CitaService::guardar($this->datos($this->paciente), canal: 'recepcion');

        $this->assertDatabaseHas('citas', ['id' => $cita->id, 'estado' => 'Pendiente', 'canal' => 'recepcion', 'turno_activo' => 1]);
    }

    public function test_el_indice_unico_impide_dos_citas_vigentes_en_el_mismo_turno(): void
    {
        $this->crearCita($this->paciente, $this->medico, '2026-10-20');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->crearCita($this->otroPaciente, $this->medico, '2026-10-20');
    }

    public function test_una_cita_cancelada_no_ocupa_el_turno(): void
    {
        $this->crearCita($this->paciente, $this->medico, '2026-10-20', '10:00:00', 'Cancelada');
        $this->crearCita($this->otroPaciente, $this->medico, '2026-10-20', '10:00:00', 'Cancelada');

        $cita = CitaService::guardar($this->datos($this->paciente));

        $this->assertSame(3, Cita::count());
        $this->assertSame(1, (int) $cita->fresh()->turno_activo);
    }

    public function test_si_otra_reserva_gana_la_carrera_el_indice_la_detiene(): void
    {
        // Simula a otro usuario que inserta el mismo turno justo después de las validaciones
        Cita::creating(function () {
            Cita::flushEventListeners();
            DB::table('citas')->insert($this->datos($this->otroPaciente) + ['estado' => 'Pendiente']);
        });

        try {
            CitaService::guardar($this->datos($this->paciente));
            $this->fail('Se esperaba una DomainException');
        } catch (\DomainException $e) {
            $this->assertSame('Este horario acaba de ser tomado por otra persona. Elige otro, por favor.', $e->getMessage());
        }

        // No quedó ninguna cita para Juan (aquí la inserción simulada comparte la transacción y también se revierte)
        $this->assertSame(0, Cita::where('paciente_id', $this->paciente)->count());
    }

    public function test_reintenta_la_reserva_ante_un_interbloqueo(): void
    {
        $intentos = 0;
        Cita::creating(function () use (&$intentos) {
            if (++$intentos === 1) {
                throw new QueryException('sqlite', 'insert into citas', [], new \Exception('Deadlock found when trying to get lock; try restarting transaction'));
            }
        });

        $cita = CitaService::guardar($this->datos($this->paciente));

        $this->assertSame(2, $intentos);
        $this->assertTrue($cita->exists);
        $this->assertSame(1, Cita::count());
    }

    public function test_tras_varios_interbloqueos_informa_que_reintente(): void
    {
        Cita::creating(fn () => throw new QueryException('sqlite', 'insert into citas', [], new \Exception('Deadlock found when trying to get lock; try restarting transaction')));

        $this->expectExceptionObject(new \DomainException('El sistema está atendiendo muchas reservas a la vez. Intenta de nuevo en unos segundos.'));

        CitaService::guardar($this->datos($this->paciente));
    }

    public function test_cancelar_exige_24_horas_salvo_que_se_fuerce(): void
    {
        // Ahora son las 09:00 del 15/10: una cita el 16/10 a las 08:30 está a 23,5 h
        $proxima = Cita::find($this->crearCita($this->paciente, $this->medico, '2026-10-16', '08:30:00'));
        $lejana = Cita::find($this->crearCita($this->paciente, $this->medico, '2026-10-16', '09:30:00'));

        $this->assertFalse($proxima->puedeCancelarse());
        $this->assertTrue($lejana->puedeCancelarse());

        try {
            CitaService::cancelar($proxima);
            $this->fail('Se esperaba una DomainException');
        } catch (\DomainException $e) {
            $this->assertSame('Las citas solo se pueden cancelar con al menos 24 horas de anticipación. Comunícate con recepción.', $e->getMessage());
        }
        $this->assertSame('Pendiente', $proxima->fresh()->estado);

        CitaService::cancelar($proxima, forzar: true);
        $this->assertSame('Cancelada', $proxima->fresh()->estado);
        $this->assertNull($proxima->fresh()->turno_activo);
    }

    public function test_cancelar_libera_el_turno_para_otro_paciente(): void
    {
        $cita = CitaService::guardar($this->datos($this->paciente));
        CitaService::cancelar($cita);

        $nueva = CitaService::guardar($this->datos($this->otroPaciente));

        $this->assertSame($this->otroPaciente, (int) $nueva->paciente_id);
    }

    public function test_no_se_cancela_una_cita_atendida(): void
    {
        $cita = Cita::find($this->crearCita($this->paciente, $this->medico, '2026-10-01', '10:00:00', 'Atendida'));

        $this->expectExceptionObject(new \DomainException('Esta cita ya no se puede cancelar.'));

        CitaService::cancelar($cita, forzar: true);
    }

    public function test_la_anticipacion_minima_es_configurable(): void
    {
        config(['clinica.cancelacion_horas_minimas' => 2]);
        $cita = Cita::find($this->crearCita($this->paciente, $this->medico, '2026-10-15', '12:00:00'));

        $this->assertTrue($cita->puedeCancelarse());
    }
}
