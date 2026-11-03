<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Http\Request;

// HU17: bitácora completa con filtros; los intentos de acceso fallidos se resaltan
class AuditoriaController extends Controller
{
    const TABLAS = ['usuarios', 'pacientes', 'citas', 'medicos', 'especialidades', 'horarios_medicos', 'historias_clinicas', 'configuraciones'];

    public function index(Request $request)
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'tabla' => ['nullable', 'in:'.implode(',', self::TABLAS)],
            'canal' => ['nullable', 'in:'.Auditoria::CANAL_WEB.','.Auditoria::CANAL_CHATBOT],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'fallos' => ['nullable', 'boolean'],
        ]);

        $registros = $this->filtrar(Auditoria::query(), $filtros)
            ->with('usuario')
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $fallos24h = Auditoria::fallosDeAcceso()->where('fecha_hora', '>=', now()->subDay())->count();

        return view('admin.auditoria.index', [
            'registros' => $registros,
            'filtros' => $filtros + ['buscar' => null, 'tabla' => null, 'canal' => null, 'desde' => null, 'hasta' => null, 'fallos' => false],
            'tablas' => self::TABLAS,
            'fallos24h' => $fallos24h,
        ]);
    }

    // Los mismos filtros que ve el administrador; las pruebas comprueban que lo mostrado coincide con esta consulta
    public static function filtrar($query, array $filtros)
    {
        $buscar = trim((string) ($filtros['buscar'] ?? ''));

        return $query
            ->when($buscar !== '', function ($q) use ($buscar) {
                $texto = '%'.addcslashes($buscar, '%_\\').'%';
                $q->where(fn ($q) => $q->where('accion', 'like', $texto)
                    ->orWhereHas('usuario', fn ($u) => $u->where('correo', 'like', $texto)));
            })
            ->when($filtros['tabla'] ?? null, fn ($q, $tabla) => $q->where('tabla_afectada', $tabla))
            ->when($filtros['canal'] ?? null, fn ($q, $canal) => $q->where('canal', $canal))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->where('fecha_hora', '>=', "{$desde} 00:00:00"))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->where('fecha_hora', '<=', "{$hasta} 23:59:59"))
            ->when(! empty($filtros['fallos']), fn ($q) => $q->fallosDeAcceso());
    }
}
