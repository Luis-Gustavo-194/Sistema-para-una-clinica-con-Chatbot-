<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;

class AdminController extends Controller
{
    // Orden fijo de los estados en el gráfico (coincide con el ENUM de citas.estado)
    const ESTADOS = ['Pendiente', 'Confirmada', 'Atendida', 'No Asistio', 'Cancelada'];

    public function index()
    {
        // Seguridad: el acceso (sesión + rol Administrador) lo controla el middleware 'rol' en routes/web.php

        $hoy = today();
        $fechaHoy = $hoy->toDateString();
        $inicioMes = $hoy->copy()->startOfMonth()->toDateString();
        $finMes = $hoy->copy()->endOfMonth()->toDateString();

        // Citas del mes agrupadas por estado (las de estado NULL cuentan como Pendiente)
        $porEstado = Cita::whereBetween('fecha', [$inicioMes, $finMes])
            ->selectRaw("coalesce(estado, 'Pendiente') as estado_real, count(*) as total")
            ->groupBy('estado_real')
            ->pluck('total', 'estado_real')
            ->map(fn ($total) => (int) $total);

        $totalMes = $porEstado->sum();
        $canceladasMes = $porEstado['Cancelada'] ?? 0;
        $atendidasMes = $porEstado['Atendida'] ?? 0;
        $noAsistioMes = $porEstado['No Asistio'] ?? 0;

        // RF-17: Recopilación de métricas clave para el reporte consolidado
        $kpis = [
            // Operación de hoy
            'citas_hoy' => Cita::noCanceladas()->where('fecha', $fechaHoy)->count(),
            'citas_proximos_7' => Cita::noCanceladas()
                ->whereBetween('fecha', [$hoy->copy()->addDay()->toDateString(), $hoy->copy()->addDays(7)->toDateString()])
                ->count(),
            'medicos_activos' => Medico::whereHas('usuario', fn ($q) => $q->where('estado', 1))->count(),
            'total_medicos' => Medico::count(),
            'total_pacientes' => Paciente::count(),
            'pacientes_nuevos_mes' => Paciente::where('created_at', '>=', $hoy->copy()->startOfMonth())->count(),

            // Gestión del mes
            'citas_mes' => $totalMes - $canceladasMes,
            'tasa_cancelacion' => $this->porcentaje($canceladasMes, $totalMes),
            'tasa_inasistencia' => $this->porcentaje($noAsistioMes, $atendidasMes + $noAsistioMes),
            'atendidas_mes' => $atendidasMes,
            'especialidades_activas' => Especialidad::where('estado', 1)->count(),
            'total_especialidades' => Especialidad::count(),
        ];

        $graficos = [
            'por_dia' => $this->citasPorDia($hoy),
            'por_estado' => [
                'etiquetas' => self::ESTADOS,
                'valores' => array_map(fn ($estado) => $porEstado[$estado] ?? 0, self::ESTADOS),
            ],
            'por_especialidad' => $this->citasPorEspecialidad($inicioMes, $finMes),
        ];

        // Médicos con más citas en el mes (sin contar canceladas)
        $topMedicos = Medico::with('especialidad')
            ->withCount([
                'citas as citas_mes' => fn ($q) => $q->noCanceladas()->whereBetween('fecha', [$inicioMes, $finMes]),
                'citas as atendidas_mes' => fn ($q) => $q->where('estado', 'Atendida')->whereBetween('fecha', [$inicioMes, $finMes]),
                'citas as canceladas_mes' => fn ($q) => $q->where('estado', 'Cancelada')->whereBetween('fecha', [$inicioMes, $finMes]),
            ])
            ->orderByDesc('citas_mes')
            ->take(5)
            ->get()
            ->filter(fn ($medico) => $medico->citas_mes > 0);

        $proximasCitas = Cita::with(['paciente', 'medico.especialidad'])
            ->noCanceladas()
            ->where('fecha', '>=', $fechaHoy)
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->take(10)
            ->get();

        $actividad = Auditoria::with('usuario')
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->take(10)
            ->get();

        return view('admin.dashboard', compact('kpis', 'graficos', 'topMedicos', 'proximasCitas', 'actividad'));
    }

    // Porcentaje con 1 decimal; null si no hay base para calcularlo (se muestra "—")
    private function porcentaje(int $parte, int $total): ?float
    {
        return $total > 0 ? round($parte * 100 / $total, 1) : null;
    }

    // Citas (sin canceladas) por día: últimos 30 días + próximos 7, rellenando con 0 los días vacíos
    private function citasPorDia($hoy): array
    {
        $desde = $hoy->copy()->subDays(29);
        $hasta = $hoy->copy()->addDays(7);

        $conteo = Cita::noCanceladas()
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->selectRaw('fecha, count(*) as total')
            ->groupBy('fecha')
            ->pluck('total', 'fecha');

        $datos = ['etiquetas' => [], 'valores' => [], 'futuro' => []];
        for ($dia = $desde->copy(); $dia->lte($hasta); $dia->addDay()) {
            $datos['etiquetas'][] = $dia->format('d/m');
            $datos['valores'][] = (int) ($conteo[$dia->toDateString()] ?? 0);
            $datos['futuro'][] = $dia->gt($hoy);
        }

        return $datos;
    }

    private function citasPorEspecialidad(string $inicioMes, string $finMes): array
    {
        $conteo = Cita::noCanceladas()
            ->join('medicos', 'medicos.id', '=', 'citas.medico_id')
            ->join('especialidades', 'especialidades.id', '=', 'medicos.especialidad_id')
            ->whereBetween('citas.fecha', [$inicioMes, $finMes])
            ->selectRaw('especialidades.nombre as nombre, count(*) as total')
            ->groupBy('especialidades.nombre')
            ->orderByDesc('total')
            ->pluck('total', 'nombre');

        return [
            'etiquetas' => $conteo->keys()->all(),
            'valores' => $conteo->values()->map(fn ($total) => (int) $total)->all(),
        ];
    }
}
