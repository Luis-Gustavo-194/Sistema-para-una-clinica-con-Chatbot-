@extends('layouts.medico')

@section('titulo', 'Mis citas')
@section('subtitulo', 'Próximas citas y el historial de atenciones.')

@section('contenido')
    <x-ui.pestanas :pestanas="[
        ['url' => route('medico.citas.index', ['pestana' => 'proximas']), 'texto' => 'Próximas', 'activa' => $pestana === 'proximas'],
        ['url' => route('medico.citas.index', ['pestana' => 'historial']), 'texto' => 'Historial', 'activa' => $pestana === 'historial'],
    ]" />

    <form method="GET" action="{{ route('medico.citas.index') }}" class="mb-5 grid gap-3 sm:grid-cols-2 lg:flex lg:items-end">
        <input type="hidden" name="pestana" value="{{ $pestana }}">
        <div class="lg:w-52">
            <x-ui.select nombre="estado" etiqueta="Estado">
                <option value="">Todos los estados</option>
                @foreach (['Pendiente' => 'Pendiente', 'Confirmada' => 'Confirmada', 'Atendida' => 'Atendida', 'No Asistio' => 'No asistió', 'Cancelada' => 'Cancelada'] as $valor => $texto)
                    <option value="{{ $valor }}" @selected($estado === $valor)>{{ $texto }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <div class="lg:w-44"><x-ui.input nombre="desde" etiqueta="Desde" type="date" :valor="$desde" /></div>
        <div class="lg:w-44"><x-ui.input nombre="hasta" etiqueta="Hasta" type="date" :valor="$hasta" /></div>
        <div class="flex gap-2">
            <x-ui.boton type="submit" variante="secundario">Filtrar</x-ui.boton>
            @if ($estado || $desde || $hasta)
                <x-ui.boton :href="route('medico.citas.index', ['pestana' => $pestana])" variante="fantasma">Limpiar</x-ui.boton>
            @endif
        </div>
    </form>

    <x-ui.tabla>
        <thead>
            <tr><th>Fecha</th><th>Horario</th><th>Paciente</th><th>DNI</th><th>Estado</th><th>Historia</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($citas as $cita)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</td>
                    <td class="whitespace-nowrap">{{ date('h:i A', strtotime($cita->hora_inicio)) }} – {{ date('h:i A', strtotime($cita->hora_fin)) }}</td>
                    <td class="text-on-surface-strong">{{ $cita->paciente->nombres }} {{ $cita->paciente->apellidos }}</td>
                    <td>{{ $cita->paciente->dni }}</td>
                    <td><x-estado-cita :cita="$cita" /></td>
                    <td>
                        @if ($cita->historiaClinica)
                            <x-ui.insignia tono="primario"><x-icono nombre="documento" class="size-3.5" /> Registrada</x-ui.insignia>
                        @else
                            —
                        @endif
                    </td>
                    <td><x-ui.boton :href="route('medico.citas.show', $cita)" variante="secundario" tamano="sm">Ver</x-ui.boton></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center">No hay citas con estos filtros.</td></tr>
            @endforelse
        </tbody>
    </x-ui.tabla>

    {{ $citas->links('vendor.pagination.clinica') }}
@endsection
