@extends('layouts.admin')

@php use App\Models\Rol; @endphp

@section('titulo', 'Usuarios')
@section('subtitulo', 'Cuentas de acceso y su rol. El rol de los médicos se gestiona desde el módulo de médicos.')

@section('contenido')
    <x-ui.tarjeta titulo="Nueva cuenta de personal" subtitulo="La persona recibirá un correo para crear su contraseña." class="mb-5">
        <form method="POST" action="{{ route('admin.usuarios.store') }}" class="grid gap-3 sm:grid-cols-2 lg:flex lg:items-end">
            @csrf
            <div class="lg:w-80"><x-ui.input nombre="correo" etiqueta="Correo *" type="email" maxlength="150" required autocomplete="off" /></div>
            <div class="lg:w-56">
                <x-ui.select nombre="rol_id" etiqueta="Rol *" required>
                    @foreach ($rolesPersonal as $id)
                        <option value="{{ $id }}" @selected((string) old('rol_id') === (string) $id)>{{ Rol::NOMBRES[$id] }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <x-ui.boton type="submit"><x-icono nombre="mas" class="size-4" /> Crear usuario</x-ui.boton>
        </form>
    </x-ui.tarjeta>

    <form method="GET" action="{{ route('admin.usuarios.index') }}" class="mb-5 grid gap-3 sm:grid-cols-2 lg:flex lg:items-end">
        <div class="lg:w-72"><x-ui.input nombre="buscar" :valor="$buscar" placeholder="Buscar por correo…" aria-label="Buscar usuario" /></div>
        <div class="lg:w-52">
            <x-ui.select nombre="rol" aria-label="Filtrar por rol">
                <option value="">Todos los roles</option>
                @foreach (Rol::NOMBRES as $id => $nombre)
                    <option value="{{ $id }}" @selected((string) $rol === (string) $id)>{{ $nombre }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <div class="flex gap-2">
            <x-ui.boton type="submit" variante="secundario">Filtrar</x-ui.boton>
            @if ($buscar !== '' || $rol)
                <x-ui.boton :href="route('admin.usuarios.index')" variante="fantasma">Limpiar</x-ui.boton>
            @endif
        </div>
    </form>

    <x-ui.tabla>
        <thead>
            <tr><th>Correo</th><th>Estado</th><th>Rol actual</th><th>Asignar rol</th></tr>
        </thead>
        <tbody>
            @forelse ($usuarios as $usuario)
                @php
                    $rolId = (int) $usuario->rol_id;
                    $esPropio = $usuario->is(Auth::user());
                    $editable = !$esPropio && $rolId !== Rol::MEDICO;
                @endphp
                <tr>
                    <td class="font-semibold text-on-surface-strong">
                        {{ $usuario->correo }}
                        @if ($esPropio)
                            <span class="text-xs font-normal">(tú)</span>
                        @endif
                    </td>
                    <td>
                        @if ($usuario->estaBloqueado())
                            <x-ui.insignia tono="peligro">🔒 Bloqueado</x-ui.insignia>
                        @elseif ($usuario->estado)
                            <x-ui.insignia tono="exito">● Activo</x-ui.insignia>
                        @else
                            <x-ui.insignia>○ Inactivo</x-ui.insignia>
                        @endif
                    </td>
                    <td>{{ Rol::NOMBRES[$rolId] ?? '—' }}</td>
                    <td>
                        @if ($editable)
                            <form method="POST" action="{{ route('admin.usuarios.rol', $usuario) }}" class="flex flex-wrap items-center gap-2"
                                  data-confirmar="{{ "¿Cambiar el rol de {$usuario->correo}? Sus permisos cambiarán de inmediato." }}">
                                @csrf
                                @method('PATCH')
                                <div class="w-44">
                                    <x-ui.select nombre="rol_id" :id="'rol_' . $usuario->id" aria-label="{{ 'Nuevo rol para ' . $usuario->correo }}">
                                        @foreach ($rolesAsignables as $id)
                                            @continue($id === Rol::PACIENTE && !$usuario->paciente)
                                            <option value="{{ $id }}" @selected($rolId === $id)>{{ Rol::NOMBRES[$id] }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </div>
                                <x-ui.boton type="submit" variante="secundario" tamano="sm">Asignar</x-ui.boton>
                            </form>
                        @else
                            <span class="text-xs">{{ $esPropio ? 'No puedes cambiar tu propio rol.' : 'Se gestiona en Médicos.' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-10 text-center">No hay usuarios que coincidan con los filtros.</td></tr>
            @endforelse
        </tbody>
    </x-ui.tabla>

    {{ $usuarios->links('vendor.pagination.clinica') }}
@endsection
