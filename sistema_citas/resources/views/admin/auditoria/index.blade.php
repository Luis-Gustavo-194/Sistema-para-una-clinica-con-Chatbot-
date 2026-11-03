@extends('layouts.admin')

@section('titulo', 'Auditoría')
@section('subtitulo', 'Inicios y cierres de sesión, intentos fallidos y acciones sobre los datos. Los DNI, correos y teléfonos se muestran enmascarados.')

@section('contenido')
    <div class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-ui.metrica titulo="Intentos de acceso fallidos (24 h)" :valor="$fallos24h" :tono="$fallos24h > 0 ? 'peligro' : 'exito'" detalle="Incluye contraseñas erradas, cuentas bloqueadas y usuarios desactivados" />
        <x-ui.metrica titulo="Registros con estos filtros" :valor="$registros->total()" />
    </div>

    <form method="GET" action="{{ route('admin.auditoria.index') }}" class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
        <div class="lg:col-span-2"><x-ui.input nombre="buscar" etiqueta="Buscar" :valor="$filtros['buscar']" placeholder="Acción o correo del usuario…" /></div>
        <x-ui.select nombre="tabla" etiqueta="Tabla">
            <option value="">Todas</option>
            @foreach ($tablas as $tabla)
                <option value="{{ $tabla }}" @selected($filtros['tabla'] === $tabla)>{{ $tabla }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.select nombre="canal" etiqueta="Canal">
            <option value="">Todos</option>
            <option value="web" @selected($filtros['canal'] === 'web')>Web</option>
            <option value="chatbot" @selected($filtros['canal'] === 'chatbot')>Chatbot</option>
        </x-ui.select>
        <x-ui.input nombre="desde" etiqueta="Desde" type="date" :valor="$filtros['desde']" />
        <x-ui.input nombre="hasta" etiqueta="Hasta" type="date" :valor="$filtros['hasta']" />
        <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-6">
            <label class="flex items-center gap-2 text-sm text-on-surface-strong">
                <input type="checkbox" name="fallos" value="1" @checked($filtros['fallos']) class="size-4 accent-primary">
                Solo intentos de acceso fallidos
            </label>
            <x-ui.boton type="submit" variante="secundario"><x-icono nombre="buscar" class="size-4" /> Filtrar</x-ui.boton>
            @if (request()->query())
                <x-ui.boton :href="route('admin.auditoria.index')" variante="fantasma">Limpiar</x-ui.boton>
            @endif
        </div>
    </form>

    <x-ui.tabla>
        <thead>
            <tr><th>Fecha y hora</th><th>Acción</th><th>Usuario</th><th>Tabla</th><th class="text-right">Registro</th><th>Canal</th><th>IP</th></tr>
        </thead>
        <tbody>
            @forelse ($registros as $registro)
                @php $fallo = $registro->esFalloDeAcceso(); @endphp
                <tr @class(['bg-danger/10' => $fallo]) @if ($fallo) data-fallo @endif>
                    <td class="whitespace-nowrap text-xs tabular-nums">{{ $registro->fecha_hora?->format('d/m/Y H:i:s') ?? '—' }}</td>
                    <td class="text-on-surface-strong">
                        @if ($fallo)
                            <x-ui.insignia tono="peligro" class="mr-1">⚠ Fallo</x-ui.insignia>
                        @endif
                        {{ $registro->accion }}
                    </td>
                    <td class="text-xs">{{ $registro->usuario->correo ?? 'Desconocido' }}</td>
                    <td class="text-xs">{{ $registro->tabla_afectada }}</td>
                    <td class="text-right tabular-nums">{{ $registro->registro_id ?? '—' }}</td>
                    <td>{{ $registro->canal === 'chatbot' ? 'Chatbot' : 'Web' }}</td>
                    <td class="text-xs tabular-nums">{{ $registro->direccion_ip ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center">No hay registros que coincidan con los filtros.</td></tr>
            @endforelse
        </tbody>
    </x-ui.tabla>

    {{ $registros->links('vendor.pagination.clinica') }}
@endsection
