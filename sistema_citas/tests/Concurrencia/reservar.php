<?php

// Proceso hijo de ReservaConcurrenteTest: arranca Laravel, espera a la marca de tiempo común
// (para que todos los procesos reserven a la vez) y llama a CitaService::guardar.
// Uso: php reservar.php <paciente_id> <medico_id> <fecha> <hora_inicio> <hora_fin> <marca_unix_float>
// Imprime una línea JSON: {"resultado": "ok"|"conflicto"|"error", "mensaje": ..., "ms": ...}

use App\Services\CitaService;
use Illuminate\Contracts\Console\Kernel;

[, $pacienteId, $medicoId, $fecha, $inicio, $fin, $marca] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tarde = microtime(true) > (float) $marca;
while (microtime(true) < (float) $marca) {
    usleep(200);
}

$inicioReloj = microtime(true);
try {
    $cita = CitaService::guardar([
        'paciente_id' => (int) $pacienteId,
        'medico_id' => (int) $medicoId,
        'fecha' => $fecha,
        'hora_inicio' => $inicio,
        'hora_fin' => $fin,
    ], canal: 'web');
    $salida = ['resultado' => 'ok', 'mensaje' => "cita #{$cita->id}"];
} catch (DomainException $e) {
    $salida = ['resultado' => 'conflicto', 'mensaje' => $e->getMessage()];
} catch (Throwable $e) {
    $salida = ['resultado' => 'error', 'mensaje' => get_class($e).': '.$e->getMessage()];
}

echo json_encode($salida + ['ms' => round((microtime(true) - $inicioReloj) * 1000, 1), 'tarde' => $tarde]), PHP_EOL;
