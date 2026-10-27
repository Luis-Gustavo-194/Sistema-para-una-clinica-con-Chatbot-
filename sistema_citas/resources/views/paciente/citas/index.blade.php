@extends('layouts.paciente')

@section('titulo', 'Mis citas')
@section('subtitulo', 'Puedes reprogramar o cancelar tus citas pendientes o confirmadas.')

@section('acciones')
    <x-ui.boton :href="route('paciente.citas.create')"><x-icono nombre="mas" class="size-4" /> Agendar cita</x-ui.boton>
@endsection

@section('contenido')
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
                            <form method="POST" action="{{ route('paciente.citas.cancelar', $cita) }}"
                                  data-confirmar="{{ '¿Cancelar tu cita del ' . \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') . ' a las ' . date('g:i A', strtotime($cita->hora_inicio)) . '?' }}">
                                @csrf @method('PATCH')
                                <x-ui.boton type="submit" variante="peligro" tamano="sm">Cancelar</x-ui.boton>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center">No tienes citas próximas. <a href="{{ route('paciente.citas.create') }}" class="font-semibold text-primary hover:underline">Agenda una aquí</a>.</td></tr>
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
