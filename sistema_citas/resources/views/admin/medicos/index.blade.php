@extends('layouts.admin')

@section('titulo', 'Médicos')
@section('subtitulo', 'Personal médico y su acceso al sistema. Los inactivos no pueden iniciar sesión ni aparecen en el chatbot.')

@section('acciones')
    <x-ui.boton :href="route('admin.medicos.create')"><x-icono nombre="mas" class="size-4" /> Nuevo médico</x-ui.boton>
@endsection

@section('contenido')
    <form method="GET" action="{{ route('admin.medicos.index') }}" class="mb-5 grid gap-3 sm:grid-cols-2 lg:flex lg:items-end">
        <div class="lg:w-72"><x-ui.input nombre="buscar" :valor="$buscar" placeholder="Nombre, apellido o colegiatura…" aria-label="Buscar médico" /></div>
        <div class="lg:w-56">
            <x-ui.select nombre="especialidad" aria-label="Filtrar por especialidad">
                <option value="">Todas las especialidades</option>
                @foreach ($especialidades as $esp)
                    <option value="{{ $esp->id }}" @selected((string) $especialidadId === (string) $esp->id)>{{ $esp->nombre }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <div class="lg:w-44">
            <x-ui.select nombre="estado" aria-label="Filtrar por estado">
                <option value="">Todos los estados</option>
                <option value="1" @selected($estado === '1')>Activos</option>
                <option value="0" @selected($estado === '0')>Inactivos</option>
            </x-ui.select>
        </div>
        <div class="flex gap-2">
            <x-ui.boton type="submit" variante="secundario">Filtrar</x-ui.boton>
            @if ($buscar !== '' || $especialidadId || in_array($estado, ['0', '1'], true))
                <x-ui.boton :href="route('admin.medicos.index')" variante="fantasma">Limpiar</x-ui.boton>
            @endif
        </div>
    </form>

    <x-ui.tabla>
        <thead>
            <tr><th>Médico</th><th>Colegiatura</th><th>Especialidad</th><th>Correo de acceso</th><th class="text-right">Citas próximas</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            @forelse ($medicos as $medico)
                @php
                    $activo = (bool) $medico->usuario?->estado;
                    $nombre = "Dr(a). {$medico->nombres} {$medico->apellidos}";
                    $aviso = $activo
                        ? "¿Desactivar a {$nombre}? No podrá iniciar sesión ni se ofrecerá en el chatbot."
                          . ($medico->citas_futuras_count > 0 ? " Tiene {$medico->citas_futuras_count} cita(s) próxima(s) que NO se cancelarán." : '')
                        : "¿Activar a {$nombre}?";
                @endphp
                <tr>
                    <td class="font-semibold text-on-surface-strong">{{ $nombre }}</td>
                    <td>{{ $medico->colegiatura }}</td>
                    <td>
                        {{ $medico->especialidad->nombre ?? '—' }}
                        @if ($medico->especialidad && !$medico->especialidad->estado)
                            <span class="text-xs">(inactiva)</span>
                        @endif
                    </td>
                    <td class="text-xs">{{ $medico->usuario->correo ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $medico->citas_futuras_count }}</td>
                    <td>
                        <div class="flex flex-col gap-1">
                            @if ($activo)
                                <x-ui.insignia tono="exito">● Activo</x-ui.insignia>
                            @else
                                <x-ui.insignia>○ Inactivo</x-ui.insignia>
                            @endif
                            @if ($medico->horarios_count === 0)
                                <x-ui.insignia tono="aviso" title="Los pacientes no pueden agendar con este médico hasta que configure sus horarios">⏰ Sin horarios</x-ui.insignia>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            <x-ui.boton :href="route('admin.medicos.edit', $medico)" variante="secundario" tamano="sm"><x-icono nombre="lapiz" class="size-3.5" /> Editar</x-ui.boton>
                            <x-ui.boton :href="route('admin.medicos.horarios.edit', $medico)" variante="secundario" tamano="sm"><x-icono nombre="reloj" class="size-3.5" /> Horarios</x-ui.boton>

                            <form method="POST" action="{{ route('admin.medicos.estado', $medico) }}" data-confirmar="{{ $aviso }}">
                                @csrf
                                @method('PATCH')
                                <x-ui.boton type="submit" variante="secundario" tamano="sm"><x-icono nombre="encendido" class="size-3.5" /> {{ $activo ? 'Desactivar' : 'Activar' }}</x-ui.boton>
                            </form>

                            <form method="POST" action="{{ route('admin.medicos.destroy', $medico) }}" data-confirmar="{{ "¿Eliminar definitivamente a {$nombre} y su usuario de acceso?" }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.boton type="submit" variante="peligro" tamano="sm" :disabled="$medico->citas_count > 0"
                                    :title="$medico->citas_count > 0 ? 'Tiene citas registradas: solo se puede desactivar' : null">
                                    <x-icono nombre="basura" class="size-3.5" /> Eliminar
                                </x-ui.boton>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center">No hay médicos que coincidan con los filtros.</td></tr>
            @endforelse
        </tbody>
    </x-ui.tabla>

    {{ $medicos->links('vendor.pagination.clinica') }}
@endsection
