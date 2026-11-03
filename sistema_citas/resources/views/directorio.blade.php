@extends('layouts.publico')

@section('titulo', 'Directorio médico')

@section('contenido')
    <section class="bg-lavanda py-14">
        <div class="mx-auto max-w-7xl px-6">
            <p class="text-sm font-bold uppercase tracking-wider text-acento-texto">Directorio</p>
            <h1 class="mt-2 text-4xl font-medium text-primary">Nuestros médicos y sus horarios</h1>
            <p class="mt-3 max-w-2xl text-on-surface">Consulta los días y horas de atención de cada especialista. {{ config('clinica.horario') }} · {{ config('clinica.telefonos.fijo') }}</p>

            <form method="GET" action="{{ route('directorio') }}" class="mt-8 grid gap-3 sm:grid-cols-[1fr_16rem_auto] sm:items-end" role="search">
                <x-ui.input nombre="buscar" :valor="$buscar" etiqueta="Buscar médico" placeholder="Nombre o apellido…" />
                <x-ui.select nombre="especialidad" etiqueta="Especialidad">
                    <option value="">Todas</option>
                    @foreach ($especialidades as $esp)
                        <option value="{{ $esp->id }}" @selected($especialidadId === $esp->id)>{{ $esp->nombre }}</option>
                    @endforeach
                </x-ui.select>
                <div class="flex gap-2">
                    <x-ui.boton type="submit"><x-icono nombre="buscar" class="size-4" /> Buscar</x-ui.boton>
                    @if ($buscar !== '' || $especialidadId)
                        <x-ui.boton :href="route('directorio')" variante="fantasma">Limpiar</x-ui.boton>
                    @endif
                </div>
            </form>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-14">
        <p class="mb-6 text-sm text-on-surface">{{ $medicos->count() }} médico(s) encontrado(s).</p>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($medicos as $medico)
                <article class="flex flex-col rounded-2xl border border-outline bg-surface p-6">
                    <div class="flex items-center gap-4">
                        <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary text-lg font-bold text-on-primary ring-4 ring-lavanda">
                            {{ mb_strtoupper(mb_substr($medico->nombres, 0, 1) . mb_substr($medico->apellidos, 0, 1)) }}
                        </span>
                        <div>
                            <h2 class="font-semibold text-on-surface-strong">Dr(a). {{ $medico->nombres }} {{ $medico->apellidos }}</h2>
                            <p class="text-sm text-primary">{{ $medico->especialidad->nombre }}</p>
                            <p class="text-xs text-on-surface">CMP {{ preg_replace('/^CMP[-\s]*/i', '', $medico->colegiatura) }}</p>
                        </div>
                    </div>

                    <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-on-surface">Horario de atención</h3>
                    @if ($medico->atencion)
                        <ul class="mt-2 space-y-1.5 text-sm text-on-surface-strong">
                            @foreach ($medico->atencion as $bloque)
                                <li class="flex flex-wrap items-center gap-x-2">
                                    <x-icono nombre="reloj" class="size-4 text-acento-texto" />
                                    <span class="font-medium">{{ $bloque['dias'] }}</span>
                                    <span class="tabular-nums">{{ $bloque['horas'] }}</span>
                                    @if ($bloque['consultorio'])<span class="text-xs text-on-surface">· {{ $bloque['consultorio'] }}</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-2 text-sm text-on-surface">Horario por confirmar.</p>
                    @endif

                    <div class="mt-auto pt-6">
                        @auth
                            <x-ui.boton :href="Auth::user()->rutaPanel()" variante="secundario" class="w-full rounded-full">Ir a mi panel para agendar</x-ui.boton>
                        @else
                            <x-ui.boton :href="route('login')" variante="secundario" class="w-full rounded-full">Agendar cita</x-ui.boton>
                        @endauth
                    </div>
                </article>
            @empty
                <p class="text-on-surface">No encontramos médicos con esos filtros.</p>
            @endforelse
        </div>
    </section>
@endsection
