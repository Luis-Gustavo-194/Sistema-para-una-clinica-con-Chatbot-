@extends('layouts.admin')

@section('titulo', 'Dashboard General')
@section('subtitulo', 'Métricas y monitoreo de la gestión de la clínica · ' . today()->format('d/m/Y'))

@php
    // Porcentaje listo para mostrar: "—" cuando no hay base para calcularlo
    $pct = fn ($valor) => $valor === null ? '—' : number_format($valor, 1) . '%';
    $mesActual = ucfirst(today()->translatedFormat('F Y'));
@endphp

@section('contenido')
    {{-- ===================== KPIs ===================== --}}
    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-on-surface">Operación</h2>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.metrica titulo="Citas de hoy" :valor="$kpis['citas_hoy']" detalle="Sin contar canceladas" />
        <x-ui.metrica titulo="Próximos 7 días" :valor="$kpis['citas_proximos_7']" detalle="Citas programadas desde mañana" tono="info" />
        <x-ui.metrica titulo="Médicos activos" :valor="$kpis['medicos_activos']" :sufijo="'/ ' . $kpis['total_medicos']"
                      :detalle="($kpis['total_medicos'] - $kpis['medicos_activos']) . ' inactivo(s)'" />
        <x-ui.metrica titulo="Pacientes" :valor="$kpis['total_pacientes']" :detalle="'+' . $kpis['pacientes_nuevos_mes'] . ' nuevo(s) este mes'" tono="exito" />
    </div>

    <h2 class="mb-3 mt-8 text-xs font-semibold uppercase tracking-wider text-on-surface">Gestión del mes · {{ $mesActual }}</h2>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.metrica titulo="Citas del mes" :valor="$kpis['citas_mes']" :detalle="$kpis['atendidas_mes'] . ' atendida(s)'" />
        <x-ui.metrica titulo="Tasa de cancelación" :valor="$pct($kpis['tasa_cancelacion'])" detalle="Canceladas sobre el total del mes" tono="peligro" />
        <x-ui.metrica titulo="Tasa de inasistencia" :valor="$pct($kpis['tasa_inasistencia'])" detalle='"No asistió" sobre citas ya realizadas' tono="aviso" />
        <x-ui.metrica titulo="Especialidades activas" :valor="$kpis['especialidades_activas']" :sufijo="'/ ' . $kpis['total_especialidades']" detalle="Disponibles en el chatbot" />
    </div>

    {{-- ===================== Gráficos (resources/js/graficos.js) ===================== --}}
    <div class="mt-8 grid gap-5 xl:grid-cols-2">
        <x-ui.tarjeta titulo="Citas por día" subtitulo="Últimos 30 días y próximos 7 · sin canceladas" class="xl:col-span-2">
            <div class="relative h-64">
                <canvas data-grafico="por-dia" data-datos='@json($graficos['por_dia'])' role="img" aria-label="Gráfico de barras de citas por día"></canvas>
            </div>
        </x-ui.tarjeta>

        <x-ui.tarjeta titulo="Citas por estado" :subtitulo="$mesActual">
            <div class="relative h-64">
                @if (array_sum($graficos['por_estado']['valores']) > 0)
                    <canvas data-grafico="por-estado" data-datos='@json($graficos['por_estado'])' role="img" aria-label="Gráfico de barras de citas por estado"></canvas>
                @else
                    <p class="flex h-full items-center justify-center text-sm text-on-surface">No hay citas registradas este mes.</p>
                @endif
            </div>
            <details class="mt-3 text-sm">
                <summary class="cursor-pointer font-medium text-primary">Ver datos</summary>
                <x-ui.tabla class="mt-2 shadow-none">
                    <thead><tr><th>Estado</th><th class="text-right">Citas</th></tr></thead>
                    <tbody>
                        @foreach ($graficos['por_estado']['etiquetas'] as $i => $estado)
                            <tr><td>{{ $estado === 'No Asistio' ? 'No asistió' : $estado }}</td><td class="text-right tabular-nums">{{ $graficos['por_estado']['valores'][$i] }}</td></tr>
                        @endforeach
                    </tbody>
                </x-ui.tabla>
            </details>
        </x-ui.tarjeta>

        <x-ui.tarjeta titulo="Citas por especialidad" :subtitulo="$mesActual . ' · sin canceladas'">
            <div class="relative h-64">
                @if (count($graficos['por_especialidad']['valores']) > 0)
                    <canvas data-grafico="por-especialidad" data-datos='@json($graficos['por_especialidad'])' role="img" aria-label="Gráfico de barras de citas por especialidad"></canvas>
                @else
                    <p class="flex h-full items-center justify-center text-sm text-on-surface">No hay citas registradas este mes.</p>
                @endif
            </div>
            <details class="mt-3 text-sm">
                <summary class="cursor-pointer font-medium text-primary">Ver datos</summary>
                <x-ui.tabla class="mt-2 shadow-none">
                    <thead><tr><th>Especialidad</th><th class="text-right">Citas</th></tr></thead>
                    <tbody>
                        @forelse ($graficos['por_especialidad']['etiquetas'] as $i => $nombre)
                            <tr><td>{{ $nombre }}</td><td class="text-right tabular-nums">{{ $graficos['por_especialidad']['valores'][$i] }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-center">Sin datos</td></tr>
                        @endforelse
                    </tbody>
                </x-ui.tabla>
            </details>
        </x-ui.tarjeta>
    </div>

    {{-- ===================== Tablas ===================== --}}
    <div class="mt-8 grid gap-5 xl:grid-cols-2">
        <section>
            <h2 class="mb-3 text-base font-semibold text-on-surface-strong">Médicos con más citas · {{ $mesActual }}</h2>
            <x-ui.tabla>
                <thead><tr><th>Médico</th><th class="text-right">Citas</th><th class="text-right">Atendidas</th><th class="text-right">Canceladas</th></tr></thead>
                <tbody>
                    @forelse ($topMedicos as $medico)
                        <tr>
                            <td>
                                <span class="font-medium text-on-surface-strong">Dr(a). {{ $medico->nombres }} {{ $medico->apellidos }}</span><br>
                                <span class="text-xs">{{ $medico->especialidad->nombre ?? '—' }}</span>
                            </td>
                            <td class="text-right tabular-nums">{{ $medico->citas_mes }}</td>
                            <td class="text-right tabular-nums">{{ $medico->atendidas_mes }}</td>
                            <td class="text-right tabular-nums">{{ $medico->canceladas_mes }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center">Sin citas este mes.</td></tr>
                    @endforelse
                </tbody>
            </x-ui.tabla>
        </section>

        <section>
            <h2 class="mb-3 text-base font-semibold text-on-surface-strong">Actividad reciente</h2>
            <x-ui.tabla>
                <thead><tr><th>Fecha</th><th>Acción</th><th>Usuario</th></tr></thead>
                <tbody>
                    @forelse ($actividad as $registro)
                        <tr>
                            <td class="whitespace-nowrap text-xs">{{ $registro->fecha_hora?->format('d/m H:i') ?? '—' }}</td>
                            <td class="text-on-surface-strong">{{ $registro->accion }}</td>
                            <td class="text-xs">{{ $registro->usuario->correo ?? 'Sistema' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center">Aún no hay acciones registradas.</td></tr>
                    @endforelse
                </tbody>
            </x-ui.tabla>
        </section>

        <section class="xl:col-span-2">
            <h2 class="mb-3 text-base font-semibold text-on-surface-strong">Próximas citas</h2>
            <x-ui.tabla>
                <thead><tr><th>Fecha</th><th>Horario</th><th>Paciente</th><th>Médico</th><th>Especialidad</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($proximasCitas as $cita)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($cita->hora_inicio)->format('h:i A') }}</td>
                            <td class="text-on-surface-strong">{{ $cita->paciente->nombres ?? '' }} {{ $cita->paciente->apellidos ?? '' }}</td>
                            <td>Dr(a). {{ $cita->medico->apellidos ?? '—' }}</td>
                            <td>{{ $cita->medico->especialidad->nombre ?? '—' }}</td>
                            <td><x-estado-cita :cita="$cita" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center">No hay citas próximas.</td></tr>
                    @endforelse
                </tbody>
            </x-ui.tabla>
        </section>
    </div>
@endsection
