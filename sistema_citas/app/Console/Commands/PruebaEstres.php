<?php

namespace App\Console\Commands;

use App\Models\Rol;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

// HU12: prueba de estrés con usuarios simultáneos por la web del paciente y por el chatbot.
// Crea datos temporales (un médico y N pacientes), lanza las peticiones en paralelo contra uno o
// varios servidores, informa tiempos y resultados, comprueba que no haya dobles reservas y borra todo.
class PruebaEstres extends Command
{
    protected $signature = 'prueba:estres
        {--usuarios=40 : Usuarios simultáneos}
        {--chat=50 : Porcentaje de usuarios que reservan por el chatbot}
        {--turnos=4 : Turnos distintos que se disputan (menos turnos = más conflictos)}
        {--url=* : URL de cada servidor (repetible); por defecto APP_URL}
        {--force : No pedir confirmación}';

    protected $description = 'Prueba de estrés: usuarios simultáneos reservando por la web y por el chatbot (HU12)';

    const CONTRASENA = 'Estres123*';

    const PREFIJO_CORREO = 'estres.';

    const DOMINIO = '@prueba.local';

    /** @var array<string, float[]> tiempos en ms por tipo de petición */
    private array $tiempos = [];

    private int $erroresHttp = 0;

    public function handle(): int
    {
        $usuarios = max(1, (int) $this->option('usuarios'));
        $urls = $this->option('url') ?: [rtrim(config('app.url'), '/')];
        $base = DB::connection()->getDatabaseName();

        $this->warn("Se crearán datos temporales en la base «{$base}» y se borrarán al terminar.");
        if (! $this->option('force') && ! $this->confirm('¿Continuar? (se recomienda usar una copia de la base)')) {
            return self::FAILURE;
        }

        $nlpActivo = $this->nlpActivo();
        $porcentajeChat = $nlpActivo ? max(0, min(100, (int) $this->option('chat'))) : 0;
        if (! $nlpActivo) {
            $this->warn('La API del chatbot (127.0.0.1:8001) no responde: solo se probará la web.');
        }

        $datos = $this->crearDatos($usuarios);

        try {
            $chat = (int) round($usuarios * $porcentajeChat / 100);
            $grupos = ['web' => array_slice($datos['pacientes'], $chat), 'chatbot' => array_slice($datos['pacientes'], 0, $chat)];

            $this->info(sprintf('Lanzando %d usuarios (%d web, %d chatbot) contra %s…', $usuarios, count($grupos['web']), count($grupos['chatbot']), implode(', ', $urls)));
            $inicio = microtime(true);
            $resultados = $this->ejecutar($grupos, $urls, $datos);
            $duracion = microtime(true) - $inicio;

            $this->informar($resultados, $datos, $duracion);
            $dobles = $this->doblesReservas($datos['medico']);

            if ($dobles > 0) {
                $this->error("Se encontraron {$dobles} turno(s) con doble reserva.");

                return self::FAILURE;
            }
            $this->info('✔ 0 dobles reservas en la base de datos.');

            return self::SUCCESS;
        } finally {
            $this->limpiar($datos);
            $this->line('Datos temporales eliminados.');
        }
    }

    // ----------------------------------------------------------------------------------------
    // Flujos: cada paso es una ronda de peticiones simultáneas (una por usuario)
    // ----------------------------------------------------------------------------------------

    private function ejecutar(array $grupos, array $urls, array $datos): array
    {
        $sesiones = [];
        foreach (['web', 'chatbot'] as $canal) {
            foreach ($grupos[$canal] as $i => $paciente) {
                $sesiones[] = [
                    'canal' => $canal,
                    'paciente' => $paciente,
                    'url' => $urls[count($sesiones) % count($urls)],
                    'cookies' => new CookieJar,
                    'token' => null,
                    'resultado' => null,
                    'reintento' => false,
                    'paso' => 0,
                ];
            }
        }

        // Ronda tras ronda, todos los usuarios que siguen activos hacen su siguiente petición a la vez
        while ($activas = array_filter($sesiones, fn ($s) => $s['resultado'] === null)) {
            $respuestas = Http::pool(function (Pool $pool) use ($activas, $datos) {
                foreach ($activas as $clave => $sesion) {
                    $this->siguientePeticion($pool, (string) $clave, $sesion, $datos);
                }
            });

            foreach ($activas as $clave => $sesion) {
                $sesiones[$clave] = $this->procesarRespuesta($sesion, $respuestas[(string) $clave] ?? null, $datos);
            }
        }

        return $sesiones;
    }

    private function cliente(Pool $pool, string $clave, array $sesion)
    {
        return $pool->as($clave)
            ->baseUrl($sesion['url'])
            ->withOptions(['cookies' => $sesion['cookies']])
            ->withoutRedirecting()
            ->timeout(60);
    }

    private function siguientePeticion(Pool $pool, string $clave, array $sesion, array $datos): void
    {
        $http = $this->cliente($pool, $clave, $sesion);
        $p = $sesion['paciente'];

        if ($sesion['canal'] === 'web') {
            match ($sesion['paso']) {
                0 => $http->get('/login'),
                1 => $http->asForm()->post('/login', ['_token' => $sesion['token'], 'correo' => $p['correo'], 'contrasena' => self::CONTRASENA]),
                2 => $http->get("/paciente/citas/nueva?especialidad={$datos['especialidad']}&medico={$datos['medico']}"),
                3 => $http->asForm()->post('/paciente/citas', ['_token' => $sesion['token'], 'medico' => $datos['medico'], 'turno' => $sesion['turno']]),
            };

            return;
        }

        // Chatbot sin sesión iniciada: se identifica con el DNI
        $mensajes = [1 => 'quiero sacar una cita médica', 2 => $p['dni'], 3 => $datos['especialidad_nombre'], 4 => (string) $datos['medico'], 5 => $sesion['opcion'] ?? '1', 6 => 'confirmar'];
        if ($sesion['paso'] === 0) {
            $http->get('/');
        } else {
            $http->withHeaders(['X-CSRF-TOKEN' => $sesion['token'], 'Accept' => 'application/json'])
                ->post('/chatbot/enviar', ['mensaje' => $mensajes[min($sesion['paso'], 6)]]);
        }
    }

    private function procesarRespuesta(array $sesion, $respuesta, array $datos): array
    {
        $tipo = $sesion['canal'].' · '.$this->nombrePaso($sesion);

        if (! $respuesta instanceof Response) {
            $this->erroresHttp++;
            $sesion['resultado'] = 'error: '.($respuesta instanceof \Throwable ? $respuesta->getMessage() : 'sin respuesta');

            return $sesion;
        }

        $this->tiempos[$tipo][] = ($respuesta->handlerStats()['total_time'] ?? 0) * 1000;

        if ($respuesta->serverError() || $respuesta->status() === 419) {
            $this->erroresHttp++;
            $sesion['resultado'] = "error: HTTP {$respuesta->status()} en {$tipo}";

            return $sesion;
        }

        $sesion['token'] = $this->token($respuesta->body()) ?? $sesion['token'];

        return $sesion['canal'] === 'web'
            ? $this->avanzarWeb($sesion, $respuesta)
            : $this->avanzarChat($sesion, $respuesta);
    }

    private function avanzarWeb(array $sesion, Response $respuesta): array
    {
        switch ($sesion['paso']) {
            case 1:
                if (! str_contains((string) $respuesta->header('Location'), '/paciente')) {
                    $sesion['resultado'] = 'error: no pudo iniciar sesión';

                    return $sesion;
                }
                break;
            case 2:
                preg_match_all('/name="turno" value="([^"]+)"/', $respuesta->body(), $m);
                $turnos = array_values(array_unique($m[1]));
                if (! $turnos) {
                    $sesion['resultado'] = 'sin turnos';

                    return $sesion;
                }
                // Tras un conflicto se elige una de las 3 alternativas destacadas (van primero); si no, uno de los turnos disputados
                $sesion['turno'] = $turnos[array_rand(array_slice($turnos, 0, $sesion['reintento'] ? 3 : max(1, (int) $this->option('turnos'))))];
                break;
            case 3:
                $destino = (string) $respuesta->header('Location');
                if (str_contains($destino, '/paciente/citas/nueva')) {
                    if ($sesion['reintento']) {
                        $sesion['resultado'] = 'conflicto';

                        return $sesion;
                    }
                    // Conflicto: vuelve al formulario (con horarios recargados y alternativas) y reintenta una vez
                    $sesion['reintento'] = true;
                    $sesion['paso'] = 2;

                    return $sesion;
                }
                $sesion['resultado'] = $sesion['reintento'] ? 'ok tras conflicto' : 'ok';

                return $sesion;
        }

        $sesion['paso']++;

        return $sesion;
    }

    private function avanzarChat(array $sesion, Response $respuesta): array
    {
        $texto = (string) $respuesta->json('respuesta');

        // El conflicto puede aparecer al elegir el horario (ya lo tomaron) o al confirmar (lo tomaron mientras tanto):
        // el chatbot ofrece alternativas, se elige una al azar y se vuelve a elegir y confirmar una sola vez
        if (in_array($sesion['paso'], [5, 6], true)
            && (str_contains($texto, 'ya no está disponible') || str_contains($texto, 'No se pudo guardar'))) {
            if ($sesion['reintento'] || ! $respuesta->json('opciones')) {
                $sesion['resultado'] = 'conflicto';

                return $sesion;
            }
            $sesion['reintento'] = true;
            $alternativas = array_column($respuesta->json('opciones'), 'valor');
            $sesion['opcion'] = (string) $alternativas[array_rand($alternativas)];
            $sesion['paso'] = 5;

            return $sesion;
        }

        if ($sesion['paso'] === 5 && ! str_contains($texto, 'Revisa los datos')) {
            $sesion['resultado'] = 'error: no se mostró el resumen';

            return $sesion;
        }

        if ($sesion['paso'] === 6) {
            $sesion['resultado'] = str_contains($texto, 'exitosamente')
                ? ($sesion['reintento'] ? 'ok tras conflicto' : 'ok')
                : 'conflicto';

            return $sesion;
        }

        if ($sesion['paso'] === 4) {
            $opciones = array_column($respuesta->json('opciones') ?? [], 'valor');
            if (! $opciones) {
                $sesion['resultado'] = 'sin turnos';

                return $sesion;
            }
            $sesion['opcion'] = (string) $opciones[array_rand(array_slice($opciones, 0, max(1, (int) $this->option('turnos'))))];
        }

        $sesion['paso']++;

        return $sesion;
    }

    private function nombrePaso(array $sesion): string
    {
        $pasos = $sesion['canal'] === 'web'
            ? ['ver login', 'iniciar sesión', 'ver horarios', 'reservar']
            : ['abrir portada', 'pedir cita', 'enviar DNI', 'elegir especialidad', 'elegir médico', 'elegir horario', 'confirmar'];

        return $pasos[$sesion['paso']] ?? 'paso '.$sesion['paso'];
    }

    // ----------------------------------------------------------------------------------------
    // Datos temporales, informe y limpieza
    // ----------------------------------------------------------------------------------------

    private function crearDatos(int $usuarios): array
    {
        return DB::transaction(function () use ($usuarios) {
            $nombre = 'Estrés '.now()->format('His');
            $especialidad = DB::table('especialidades')->insertGetId(['nombre' => $nombre, 'descripcion' => 'Temporal (prueba:estres)', 'estado' => 1]);
            $usuarioMedico = DB::table('usuarios')->insertGetId(['rol_id' => Rol::MEDICO, 'correo' => self::PREFIJO_CORREO.'medico'.self::DOMINIO, 'contrasena' => Hash::make(self::CONTRASENA, ['rounds' => 4]), 'estado' => 1]);
            $medico = DB::table('medicos')->insertGetId(['usuario_id' => $usuarioMedico, 'especialidad_id' => $especialidad, 'colegiatura' => 'ESTRES-'.now()->format('His'), 'nombres' => 'Prueba', 'apellidos' => 'Estrés']);

            for ($dia = 1; $dia <= 7; $dia++) {
                DB::table('horarios_medicos')->insert(['medico_id' => $medico, 'dia_semana' => $dia, 'hora_inicio' => '08:00:00', 'hora_fin' => '20:00:00', 'duracion_consulta_minutos' => 30]);
            }

            $hash = Hash::make(self::CONTRASENA, ['rounds' => 4]);
            $pacientes = [];
            for ($i = 0; $i < $usuarios; $i++) {
                $correo = self::PREFIJO_CORREO.$i.self::DOMINIO;
                $usuarioId = DB::table('usuarios')->insertGetId(['rol_id' => Rol::PACIENTE, 'correo' => $correo, 'contrasena' => $hash, 'estado' => 1]);
                $dni = (string) (99000000 + $i);
                $pacienteId = DB::table('pacientes')->insertGetId(['usuario_id' => $usuarioId, 'dni' => $dni, 'nombres' => "Estrés{$i}", 'apellidos' => 'Temporal']);
                $pacientes[] = ['id' => $pacienteId, 'usuario_id' => $usuarioId, 'correo' => $correo, 'dni' => $dni];
            }

            return ['especialidad' => $especialidad, 'especialidad_nombre' => $nombre, 'usuario_medico' => $usuarioMedico, 'medico' => $medico, 'pacientes' => $pacientes];
        });
    }

    private function informar(array $sesiones, array $datos, float $duracion): void
    {
        $this->newLine();
        $this->info(sprintf('Duración total: %.1f s · Peticiones con error HTTP: %d', $duracion, $this->erroresHttp));

        $filas = [];
        foreach ($this->tiempos as $tipo => $ms) {
            sort($ms);
            $filas[] = [$tipo, count($ms), $this->percentil($ms, 50), $this->percentil($ms, 95), round(end($ms), 1)];
        }
        $this->table(['Petición', 'Cantidad', 'p50 (ms)', 'p95 (ms)', 'Máx (ms)'], $filas);

        $resumen = [];
        foreach ($sesiones as $s) {
            $clave = $s['canal'].'|'.(str_starts_with((string) $s['resultado'], 'error') ? 'error' : $s['resultado']);
            $resumen[$clave] = ($resumen[$clave] ?? 0) + 1;
        }
        ksort($resumen);
        $this->table(['Canal', 'Resultado', 'Usuarios'], array_map(fn ($k, $v) => [...explode('|', $k), $v], array_keys($resumen), $resumen));

        foreach (array_unique(array_filter(array_column($sesiones, 'resultado'), fn ($r) => str_starts_with((string) $r, 'error'))) as $error) {
            $this->line("  · {$error}");
        }

        $porCanal = DB::table('citas')->where('medico_id', $datos['medico'])->whereNotNull('turno_activo')
            ->select('canal', DB::raw('COUNT(*) as total'))->groupBy('canal')->pluck('total', 'canal')->all();
        $this->line('Citas guardadas por canal: '.json_encode($porCanal));
    }

    private function doblesReservas(int $medico): int
    {
        return DB::table('citas')->where('medico_id', $medico)->whereNotNull('turno_activo')
            ->select('fecha', 'hora_inicio')->groupBy('fecha', 'hora_inicio')->havingRaw('COUNT(*) > 1')->get()->count();
    }

    private function limpiar(array $datos): void
    {
        $usuarios = array_merge(array_column($datos['pacientes'], 'usuario_id'), [$datos['usuario_medico']]);
        $citas = DB::table('citas')->where('medico_id', $datos['medico'])->pluck('id');

        DB::transaction(function () use ($datos, $usuarios, $citas) {
            DB::table('auditorias')->whereIn('usuario_id', $usuarios)->delete();
            DB::table('auditorias')->where('tabla_afectada', 'citas')->whereIn('registro_id', $citas)->delete();
            DB::table('citas')->where('medico_id', $datos['medico'])->delete();
            DB::table('horarios_medicos')->where('medico_id', $datos['medico'])->delete();
            DB::table('pacientes')->whereIn('id', array_column($datos['pacientes'], 'id'))->delete();
            DB::table('medicos')->where('id', $datos['medico'])->delete();
            DB::table('usuarios')->whereIn('id', $usuarios)->delete();
            DB::table('especialidades')->where('id', $datos['especialidad'])->delete();
        });
    }

    private function nlpActivo(): bool
    {
        try {
            return Http::timeout(3)->post('http://127.0.0.1:8001/predecir', ['texto' => 'hola'])->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function token(string $html): ?string
    {
        return preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m) ? $m[1] : null;
    }

    private function percentil(array $ordenados, int $p): float
    {
        return round($ordenados[max(0, (int) ceil($p / 100 * count($ordenados)) - 1)] ?? 0, 1);
    }
}
