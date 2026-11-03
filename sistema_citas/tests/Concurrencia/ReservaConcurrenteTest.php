<?php

namespace Tests\Concurrencia;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\Concerns\EsquemaClinica;
use Tests\TestCase;

// HU12: N procesos PHP reservan a la vez contra MySQL real (InnoDB), no contra SQLite.
// Usa una base aparte, sistema_citas_testing, que se crea y se vacía sola; nunca toca la base real.
// Ejecutar: php artisan test --testsuite=Concurrencia
class ReservaConcurrenteTest extends TestCase
{
    use EsquemaClinica;

    const BASE = 'sistema_citas_testing';

    const PROCESOS = 20;

    private int $medico;

    /** @var int[] */
    private array $pacientes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $mysql = config('database.connections.mysql');
        try {
            $pdo = new PDO("mysql:host={$mysql['host']};port={$mysql['port']}", $mysql['username'], $mysql['password']);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.self::BASE.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        } catch (\PDOException $e) {
            $this->markTestSkipped('MySQL no disponible para la prueba de concurrencia: '.$e->getMessage());
        }

        config(['database.connections.mysql.database' => self::BASE, 'database.default' => 'mysql']);
        DB::purge('mysql');
        Schema::dropAllTables();
        $this->crearEsquemaClinica();

        $this->medico = $this->crearMedico($this->crearEspecialidad('Cardiología'), 'doc@x.com');
        for ($i = 0; $i < self::PROCESOS; $i++) {
            $this->pacientes[] = $this->crearPaciente((string) (90000000 + $i), "Paciente{$i}");
        }
    }

    protected function tearDown(): void
    {
        if (config('database.connections.mysql.database') === self::BASE) {
            Schema::dropAllTables();
        }

        parent::tearDown();
    }

    // Lanza un proceso por solicitud; todos esperan la misma marca de tiempo y reservan a la vez
    private function reservarEnParalelo(array $solicitudes): array
    {
        $marca = microtime(true) + 6; // tiempo para que todos terminen de arrancar Laravel
        $entorno = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => self::BASE,
            'MAIL_MAILER' => 'array',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ]);

        $procesos = [];
        foreach ($solicitudes as $s) {
            $comando = [PHP_BINARY, __DIR__.'/reservar.php', $s['paciente'], $this->medico, $s['fecha'], $s['inicio'], $s['fin'], (string) $marca];
            $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, base_path(), $entorno);
            $this->assertIsResource($proceso, 'No se pudo lanzar el proceso hijo');
            $procesos[] = [$proceso, $tuberias];
        }

        $resultados = [];
        foreach ($procesos as [$proceso, $tuberias]) {
            $salida = stream_get_contents($tuberias[1]);
            $errores = stream_get_contents($tuberias[2]);
            fclose($tuberias[1]);
            fclose($tuberias[2]);
            proc_close($proceso);

            $resultado = json_decode(trim((string) strrchr("\n".trim($salida), "\n")), true);
            $resultados[] = $resultado ?? ['resultado' => 'error', 'mensaje' => trim($salida."\n".$errores)];
        }

        $tarde = array_filter($resultados, fn ($r) => ! empty($r['tarde']));
        $this->assertCount(0, $tarde, 'Algún proceso arrancó después de la marca: la prueba no fue simultánea');

        return $resultados;
    }

    private function contar(array $resultados): array
    {
        $conteo = ['ok' => 0, 'conflicto' => 0, 'error' => 0];
        foreach ($resultados as $r) {
            $conteo[$r['resultado']]++;
        }

        return $conteo;
    }

    public function test_n_solicitudes_simultaneas_por_el_mismo_turno_solo_una_gana(): void
    {
        $solicitudes = array_map(fn ($p) => ['paciente' => $p, 'fecha' => '2030-01-15', 'inicio' => '10:00:00', 'fin' => '10:30:00'], $this->pacientes);

        $resultados = $this->reservarEnParalelo($solicitudes);
        $conteo = $this->contar($resultados);

        $errores = array_column(array_filter($resultados, fn ($r) => $r['resultado'] === 'error'), 'mensaje');
        $this->assertSame(0, $conteo['error'], "Errores inesperados:\n".implode("\n", $errores));
        $this->assertSame(1, $conteo['ok'], 'Debe haber exactamente una reserva correcta');
        $this->assertSame(self::PROCESOS - 1, $conteo['conflicto']);
        $this->assertSame(1, DB::table('citas')->where('fecha', '2030-01-15')->where('hora_inicio', '10:00:00')->count());
    }

    public function test_n_solicitudes_simultaneas_por_turnos_distintos_del_mismo_medico_todas_ganan(): void
    {
        $solicitudes = [];
        foreach ($this->pacientes as $i => $paciente) {
            $inicio = sprintf('%02d:%02d:00', 8 + intdiv($i * 20, 60), ($i * 20) % 60);
            $fin = sprintf('%02d:%02d:00', 8 + intdiv($i * 20 + 20, 60), ($i * 20 + 20) % 60);
            $solicitudes[] = ['paciente' => $paciente, 'fecha' => '2030-01-16', 'inicio' => $inicio, 'fin' => $fin];
        }

        $resultados = $this->reservarEnParalelo($solicitudes);
        $conteo = $this->contar($resultados);

        $errores = array_column(array_filter($resultados, fn ($r) => $r['resultado'] !== 'ok'), 'mensaje');
        $this->assertSame(['ok' => self::PROCESOS, 'conflicto' => 0, 'error' => 0], $conteo, "Fallos:\n".implode("\n", $errores));
        $this->assertSame(self::PROCESOS, DB::table('citas')->where('fecha', '2030-01-16')->count());
    }

    public function test_turnos_que_se_cruzan_sin_empezar_a_la_misma_hora_no_se_duplican(): void
    {
        // La mitad pide 10:00-10:30 y la otra 10:15-10:45: se cruzan aunque el índice único no los vea iguales
        $solicitudes = [];
        foreach ($this->pacientes as $i => $paciente) {
            $solicitudes[] = $i % 2 === 0
                ? ['paciente' => $paciente, 'fecha' => '2030-01-17', 'inicio' => '10:00:00', 'fin' => '10:30:00']
                : ['paciente' => $paciente, 'fecha' => '2030-01-17', 'inicio' => '10:15:00', 'fin' => '10:45:00'];
        }

        $conteo = $this->contar($this->reservarEnParalelo($solicitudes));

        $this->assertSame(['ok' => 1, 'conflicto' => self::PROCESOS - 1, 'error' => 0], $conteo);
        $this->assertSame(1, DB::table('citas')->where('fecha', '2030-01-17')->count());
    }
}
