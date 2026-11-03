@extends('layouts.paciente')

@section('titulo', 'Mis citas')
@section('subtitulo', 'Puedes reprogramar tus citas pendientes o confirmadas, y cancelarlas con al menos ' . config('clinica.cancelacion_horas_minimas') . ' horas de anticipación.')

@section('acciones')
    <x-ui.boton :href="route('paciente.citas.create')"><x-icono nombre="mas" class="size-4" /> Agendar cita</x-ui.boton>
@endsection

@section('contenido')
    @php $horasMinimas = config('clinica.cancelacion_horas_minimas'); @endphp

    <form method="GET" action="{{ route('paciente.citas.index') }}" class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end" x-data>
        <div class="sm:w-60">
            <x-ui.select nombre="estado" etiqueta="Filtrar por estado" x-on:change="$el.form.submit()">
                <option value="">Todos los estados</option>
                @foreach (\App\Models\Cita::ESTADOS as $valor => $texto)
                    <option value="{{ $valor }}" @selected($estado === $valor)>{{ $texto }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <noscript><x-ui.boton type="submit" variante="secundario">Filtrar</x-ui.boton></noscript>
        @if ($estado)
            <x-ui.boton :href="route('paciente.citas.index')" variante="fantasma">Ver todas</x-ui.boton>
        @endif
    </form>

    <h2 class="mb-3 text-base font-semibold text-on-surface-strong">Próximas</h2>
    <x-ui.tabla>
        <thead><tr><th>Fecha</th><th>Hora</th><th>Médico</th><th>Especialidad</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
            @forelse ($proximas as $cita)
                <tr>
                    <td class="font-medium text-on-surface-strong">{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</td>
                    <td>{{ date('g:i A', strtotime($cita->hora_inicio)) }}</td>
                    <td>Dr(a). {{ $cita->medico->nombres }} {{ $cita->medico->apellidos }}</td>
                    <td>{{ $cita->medico->especialidad->nombre ?? '—' }}</td>
                    <td><x-estado-cita :cita="$cita" /></td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            <x-ui.boton :href="route('paciente.citas.edit', $cita)" variante="secundario" tamano="sm">Reprogramar</x-ui.boton>
                            @if ($cita->puedeCancelarse())
                                <form method="POST" action="{{ route('paciente.citas.cancelar', $cita) }}"
                                      data-confirmar="{{ '¿Cancelar tu cita del ' . \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') . ' a las ' . date('g:i A', strtotime($cita->hora_inicio)) . '? El horario quedará libre para otro paciente.' }}">
                                    @csrf @method('PATCH')
                                    <x-ui.boton type="submit" variante="peligro" tamano="sm">Cancelar</x-ui.boton>
                                </form>
                            @else
                                <x-ui.boton variante="peligro" tamano="sm" disabled :title="'Solo se puede cancelar con ' . $horasMinimas . ' h de anticipación. Comunícate con recepción.'">Cancelar</x-ui.boton>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center">{{ $estado ? 'No tienes citas próximas con ese estado.' : 'No tienes citas próximas.' }} <a href="{{ route('paciente.citas.create') }}" class="font-semibold text-primary hover:underline">Agenda una aquí</a>.</td></tr>
            @endforelse
        </tbody>
    </x-ui.tabla>

    <h2 class="mb-3 mt-8 text-base font-semibold text-on-surface-strong">Historial</h2>
    <x-ui.tabla>
        <thead><tr><th>Fecha</th><th>Hora</th><th>Médico</th><th>Especialidad</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($historial as $cita)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</td>
                    <td>{{ date('g:i A', strtotime($cita->hora_inicio)) }}</td>
                    <td>Dr(a). {{ $cita->medico->nombres }} {{ $cita->medico->apellidos }}</td>
                    <td>{{ $cita->medico->especialidad->nombre ?? '—' }}</td>
                    <td><x-estado-cita :cita="$cita" /></td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center">Sin citas anteriores.</td></tr>
            @endforelse
        </tbody>
    </x-ui.tabla>
@endsection
