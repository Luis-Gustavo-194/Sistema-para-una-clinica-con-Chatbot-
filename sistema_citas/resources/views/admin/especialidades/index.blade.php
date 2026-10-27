@extends('layouts.admin')

@section('titulo', 'Especialidades')
@section('subtitulo', 'Áreas médicas que ofrece la clínica. Las inactivas no se muestran en el chatbot.')

@section('acciones')
    <x-ui.boton :href="route('admin.especialidades.create')"><x-icono nombre="mas" class="size-4" /> Nueva especialidad</x-ui.boton>
@endsection

@section('contenido')
    <form method="GET" action="{{ route('admin.especialidades.index') }}" class="mb-5 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-72">
            <x-ui.input nombre="buscar" :valor="$buscar" placeholder="Buscar por nombre…" aria-label="Buscar por nombre" />
        </div>
        <x-ui.boton type="submit" variante="secundario"><x-icono nombre="buscar" class="size-4" /> Buscar</x-ui.boton>
        @if ($buscar !== '')
            <x-ui.boton :href="route('admin.especialidades.index')" variante="fantasma">Limpiar</x-ui.boton>
        @endif
    </form>

    <x-ui.tabla>
        <thead>
            <tr><th>Nombre</th><th>Descripción</th><th class="text-right">Médicos</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($especialidades as $especialidad)
                <tr>
                    <td class="font-semibold text-on-surface-strong">{{ $especialidad->nombre }}</td>
                    <td class="max-w-sm">{{ \Illuminate\Support\Str::limit($especialidad->descripcion, 80) ?: '—' }}</td>
                    <td class="text-right tabular-nums">{{ $especialidad->medicos_count }}</td>
                    <td>
                        @if ($especialidad->estado)
                            <x-ui.insignia tono="exito">● Activa</x-ui.insignia>
                        @else
                            <x-ui.insignia>○ Inactiva</x-ui.insignia>
                        @endif
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            <x-ui.boton :href="route('admin.especialidades.edit', $especialidad)" variante="secundario" tamano="sm"><x-icono nombre="lapiz" class="size-3.5" /> Editar</x-ui.boton>

                            <form method="POST" action="{{ route('admin.especialidades.estado', $especialidad) }}"
                                  data-confirmar="{{ ($especialidad->estado ? '¿Desactivar' : '¿Activar') . ' la especialidad «' . $especialidad->nombre . '»?' }}">
                                @csrf
                                @method('PATCH')
                                <x-ui.boton type="submit" variante="secundario" tamano="sm"><x-icono nombre="encendido" class="size-3.5" /> {{ $especialidad->estado ? 'Desactivar' : 'Activar' }}</x-ui.boton>
                            </form>

                            <form method="POST" action="{{ route('admin.especialidades.destroy', $especialidad) }}"
                                  data-confirmar="{{ '¿Eliminar definitivamente la especialidad «' . $especialidad->nombre . '»?' }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.boton type="submit" variante="peligro" tamano="sm" :disabled="$especialidad->medicos_count > 0"
                                    :title="$especialidad->medicos_count > 0 ? 'Tiene médicos asignados: solo se puede desactivar' : null">
                                    <x-icono nombre="basura" class="size-3.5" /> Eliminar
                                </x-ui.boton>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="py-10 text-center">
                        {{ $buscar !== '' ? 'No hay especialidades que coincidan con la búsqueda.' : 'Aún no hay especialidades registradas.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.tabla>
@endsection
