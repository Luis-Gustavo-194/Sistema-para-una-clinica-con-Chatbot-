@extends('layouts.paciente')

@php $reprogramando = $cita !== null; @endphp

@section('titulo', $reprogramando ? 'Reprogramar cita' : 'Agendar cita')
@section('subtitulo', $reprogramando
    ? 'Cita actual: ' . \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') . ' a las ' . date('g:i A', strtotime($cita->hora_inicio)) . ' con Dr(a). ' . $cita->medico->apellidos . '. Elige el nuevo horario.'
    : 'Elige la especialidad, el médico y un horario libre de los próximos ' . \App\Services\DisponibilidadService::DIAS_BUSQUEDA . ' días.')

@if ($reprogramando)
    @section('migas')
        <li><a href="{{ route('paciente.citas.index') }}" class="font-medium text-on-surface-strong hover:text-primary">Mis citas</a></li>
        <li aria-hidden="true">/</li>
        <li aria-current="page">Reprogramar</li>
    @endsection
@endif

@section('acciones')
    <x-ui.boton :href="route('paciente.citas.index')" variante="secundario"><x-icono nombre="chevron-izquierda" class="size-4" /> Mis citas</x-ui.boton>
@endsection

@section('contenido')
    <x-ui.tarjeta>
        {{-- Pasos 1 y 2: al cambiar la selección se recarga la página por GET (Alpine envía el formulario) --}}
        <form method="GET" action="{{ $reprogramando ? route('paciente.citas.edit', $cita) : route('paciente.citas.create') }}"
              x-data class="grid gap-5 md:grid-cols-2">
            <x-ui.select nombre="especialidad" etiqueta="1. Especialidad" x-on:change="$el.form.medico.value = ''; $el.form.submit()">
                <option value="">Selecciona…</option>
                @foreach ($especialidades as $esp)
                    <option value="{{ $esp->id }}" @selected($especialidadId === $esp->id)>{{ $esp->nombre }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select nombre="medico" etiqueta="2. Médico" x-on:change="$el.form.submit()" :disabled="!$especialidadId">
                <option value="">{{ $especialidadId ? ($medicos->isEmpty() ? 'Sin médicos disponibles' : 'Selecciona…') : 'Elige primero la especialidad' }}</option>
                @foreach ($medicos as $med)
                    <option value="{{ $med->id }}" @selected($medico?->id === $med->id)>Dr(a). {{ $med->nombres }} {{ $med->apellidos }}</option>
                @endforeach
            </x-ui.select>
            <noscript><x-ui.boton type="submit" variante="secundario">Ver horarios</x-ui.boton></noscript>
        </form>

        {{-- Paso 3: elegir turno y confirmar --}}
        @if ($medico)
            <form method="POST" action="{{ $reprogramando ? route('paciente.citas.update', $cita) : route('paciente.citas.store') }}" class="mt-8">
                @csrf
                @if ($reprogramando) @method('PUT') @endif
                <input type="hidden" name="medico" value="{{ $medico->id }}">

                <h2 class="mb-4 text-base font-semibold text-on-surface-strong">3. Horario con Dr(a). {{ $medico->apellidos }}</h2>

                @if ($sinHorarios)
                    <x-ui.alerta tono="aviso">Este médico aún no tiene horarios de atención configurados. Elige otro médico.</x-ui.alerta>
                @elseif (empty($dias))
                    <x-ui.alerta tono="info">No hay turnos libres en los próximos {{ \App\Services\DisponibilidadService::DIAS_BUSQUEDA }} días. Prueba con otro médico.</x-ui.alerta>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($dias as $fecha => $turnos)
                            <fieldset class="rounded-radius border border-outline p-4">
                                <legend class="px-1 text-sm font-semibold text-on-surface-strong">
                                    {{ ucfirst(\Carbon\Carbon::parse($fecha)->translatedFormat('l d/m')) }}
                                    @if ($turnos[0]['consultorio'])<span class="font-normal text-on-surface">· {{ $turnos[0]['consultorio'] }}</span>@endif
                                </legend>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($turnos as $turno)
                                        @php $valor = $fecha . '|' . $turno['inicio']; @endphp
                                        <label>
                                            <input type="radio" name="turno" value="{{ $valor }}" required @checked(old('turno') === $valor) class="turno-radio sr-only">
                                            <span class="inline-block cursor-pointer rounded-full border border-primary/25 bg-lavanda px-3 py-1.5 text-sm text-primary transition hover:border-primary">{{ date('g:i A', strtotime($turno['inicio'])) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    @error('turno')
                        <p class="mt-3 text-sm font-medium text-danger">{{ $message }}</p>
                    @enderror

                    {{-- El botón queda visible aunque haya muchos días con turnos --}}
                    <div class="sticky bottom-0 mt-6 border-t border-outline bg-surface py-4">
                        <x-ui.boton type="submit" tamano="lg">{{ $reprogramando ? 'Confirmar nuevo horario' : 'Confirmar cita' }}</x-ui.boton>
                    </div>
                @endif
            </form>
        @endif
    </x-ui.tarjeta>
@endsection
