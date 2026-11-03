@extends('layouts.recepcion')

@section('titulo', 'Pacientes')
@section('subtitulo', 'Busca por DNI o por nombres y apellidos. Basta con escribir el inicio (por ejemplo "7000" o "Quis").')

@section('acciones')
    <x-ui.boton :href="route('recepcion.pacientes.create')"><x-icono nombre="mas" class="size-4" /> Registrar paciente</x-ui.boton>
@endsection

@section('contenido')
    <form method="GET" action="{{ route('recepcion.pacientes.index') }}" class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end" role="search">
        <div class="sm:w-96"><x-ui.input nombre="buscar" :valor="$buscar" placeholder="DNI, nombres o apellidos…" aria-label="Buscar paciente" autofocus /></div>
        <div class="flex gap-2">
            <x-ui.boton type="submit" variante="secundario"><x-icono nombre="buscar" class="size-4" /> Buscar</x-ui.boton>
            @if ($buscar !== '')
                <x-ui.boton :href="route('recepcion.pacientes.index')" variante="fantasma">Limpiar</x-ui.boton>
            @endif
        </div>
    </form>

    @if ($buscar !== '')
        <p class="mb-3 text-sm text-on-surface">{{ $pacientes->total() }} resultado(s) para «{{ $buscar }}».</p>
    @endif

    <x-ui.tabla>
        <thead>
            <tr><th>Paciente</th><th>DNI</th><th>Teléfono</th><th>Seguro</th><th>Correo de acceso</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($pacientes as $paciente)
                <tr>
                    <td class="font-semibold text-on-surface-strong">{{ $paciente->apellidos }}, {{ $paciente->nombres }}</td>
                    <td class="tabular-nums">{{ $paciente->dni }}</td>
                    <td>{{ $paciente->telefono ?: '—' }}</td>
                    <td>{{ $paciente->seguro ?: '—' }}</td>
                    <td class="text-xs">{{ $paciente->usuario->correo ?? '—' }}</td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            <x-ui.boton :href="route('recepcion.pacientes.show', $paciente)" variante="secundario" tamano="sm"><x-icono nombre="ojo" class="size-3.5" /> Ver ficha</x-ui.boton>
                            <x-ui.boton :href="route('recepcion.pacientes.edit', $paciente)" variante="secundario" tamano="sm"><x-icono nombre="lapiz" class="size-3.5" /> Editar</x-ui.boton>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-10 text-center">
                        @if ($buscar !== '')
                            <p>No se encontraron pacientes para «{{ $buscar }}».</p>
                            <x-ui.boton :href="route('recepcion.pacientes.create')" variante="fantasma" class="mt-2"><x-icono nombre="mas" class="size-4" /> Registrar paciente nuevo</x-ui.boton>
                        @else
                            Aún no hay pacientes registrados.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.tabla>

    {{ $pacientes->links('vendor.pagination.clinica') }}
@endsection
