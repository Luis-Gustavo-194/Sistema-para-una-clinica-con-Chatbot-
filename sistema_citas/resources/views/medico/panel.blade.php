@extends('layouts.medico')

@section('titulo', 'Agenda de hoy')
@section('subtitulo', ucfirst(today()->translatedFormat('l d \d\e F \d\e Y')))

@section('acciones')
    <x-ui.boton :href="route('medico.citas.index')" variante="secundario">Ver todas mis citas</x-ui.boton>
@endsection

@section('contenido')
    @if ($sinHorarios)
        <x-ui.alerta tono="aviso" class="mb-5">
            Aún no configuraste tus horarios de atención, por eso los pacientes no pueden agendar contigo.
            <a href="{{ route('medico.horarios.edit') }}" class="font-semibold text-primary underline">Configurar horarios</a>
        </x-ui.alerta>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.metrica titulo="Citas de hoy" :valor="$kpis['citas_hoy']" :detalle="$kpis['por_atender_hoy'] . ' por atender'" tono="info" />
        <x-ui.metrica titulo="Esta semana" :valor="$kpis['citas_semana']" detalle="Citas sin contar canceladas" />
        <x-ui.metrica titulo="Atendidas del mes" :valor="$kpis['atendidas_mes']" :detalle="$kpis['pacientes_mes'] . ' paciente(s) con cita este mes'" tono="exito" />
        <x-ui.metrica titulo="Inasistencia del mes" :valor="$kpis['tasa_inasistencia'] === null ? '—' : number_format($kpis['tasa_inasistencia'], 1) . '%'" detalle='"No asistió" sobre citas realizadas' tono="aviso" />
    </div>

    <h2 class="mb-3 mt-8 text-base font-semibold text-on-surface-strong">Pacientes de hoy</h2>
    <x-ui.tabla>
        <thead>
            <tr><th>Hora</th><th>Paciente</th><th class="text-right">Edad</th><th>Teléfono</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($agenda as $cita)
                <tr>
                    <td class="whitespace-nowrap"><span class="font-semibold text-on-surface-strong">{{ date('h:i A', strtotime($cita->hora_inicio)) }}</span> <span class="text-xs">– {{ date('h:i A', strtotime($cita->hora_fin)) }}</span></td>
                    <td><span class="font-medium text-on-surface-strong">{{ $cita->paciente->nombres }} {{ $cita->paciente->apellidos }}</span><br><span class="text-xs">DNI {{ $cita->paciente->dni }}</span></td>
                    <td class="text-right tabular-nums">{{ $cita->paciente->edad ?? '—' }}</td>
                    <td>{{ $cita->paciente->telefono ?: '—' }}</td>
                    <td><x-estado-cita :cita="$cita" /></td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            <x-ui.boton :href="route('medico.citas.show', $cita)" tamano="sm">{{ $cita->estado === 'Atendida' ? 'Ver atención' : 'Atender' }}</x-ui.boton>
                            @if (in_array($cita->estado ?? 'Pendiente', ['Pendiente', 'Confirmada'], true))
                                <form method="POST" action="{{ route('medico.citas.estado', $cita) }}" data-confirmar="¿Marcar que el paciente no asistió?">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="estado" value="No Asistio">
                                    <x-ui.boton type="submit" variante="secundario" tamano="sm">No asistió</x-ui.boton>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center">No tienes citas programadas para hoy.</td></tr>
            @endforelse
        </tbody>
    </x-ui.tabla>

    <h2 class="mb-3 mt-8 text-base font-semibold text-on-surface-strong">Próximas citas</h2>
    <x-ui.tabla>
        <thead>
            <tr><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($proximas as $cita)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</td>
                    <td>{{ date('h:i A', strtotime($cita->hora_inicio)) }}</td>
                    <td class="text-on-surface-strong">{{ $cita->paciente->nombres }} {{ $cita->paciente->apellidos }}</td>
                    <td><x-estado-cita :cita="$cita" /></td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            <x-ui.boton :href="route('medico.citas.show', $cita)" variante="secundario" tamano="sm">Ver</x-ui.boton>
                            @if (($cita->estado ?? 'Pendiente') === 'Pendiente')
                                <form method="POST" action="{{ route('medico.citas.estado', $cita) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="estado" value="Confirmada">
                                    <x-ui.boton type="submit" variante="exito" tamano="sm"><x-icono nombre="check" class="size-3.5" /> Confirmar</x-ui.boton>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center">No tienes citas próximas.</td></tr>
            @endforelse
        </tbody>
    </x-ui.tabla>
@endsection
