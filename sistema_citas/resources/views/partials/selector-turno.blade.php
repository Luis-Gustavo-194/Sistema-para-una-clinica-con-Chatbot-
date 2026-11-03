{{--
    Selector especialidad → médico → turno libre. Lo usan paciente/citas/form y recepcion/citas/form.
    Recibe: $rutaGet, $rutaPost, $metodoPost (null o 'PUT'), $textoBoton y los datos de SeleccionTurno::datosSelector().
    Ante un conflicto, el controlador vuelve aquí con los horarios recargados y session('alternativas').
--}}
@php $alternativas = session('alternativas', []); @endphp

<x-ui.tarjeta>
    {{-- Pasos 1 y 2: al cambiar la selección se recarga la página por GET (Alpine envía el formulario) --}}
    <form method="GET" action="{{ $rutaGet }}" x-data class="grid gap-5 md:grid-cols-2">
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
        <form method="POST" action="{{ $rutaPost }}" class="mt-8">
            @csrf
            @if ($metodoPost) @method($metodoPost) @endif
            <input type="hidden" name="medico" value="{{ $medico->id }}">

            <h2 class="mb-4 text-base font-semibold text-on-surface-strong">3. Horario con Dr(a). {{ $medico->apellidos }}</h2>

            @error('turno')
                <x-ui.alerta tono="aviso" class="mb-4">{{ $message }}</x-ui.alerta>
            @enderror

            @if ($alternativas)
                <fieldset class="mb-6 rounded-radius border-2 border-secondary/50 bg-secondary/5 p-4">
                    <legend class="px-1 text-sm font-semibold text-on-surface-strong">Horarios cercanos libres</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($alternativas as $alternativa)
                            @php $valor = $alternativa['fecha'] . '|' . $alternativa['inicio']; @endphp
                            <label>
                                <input type="radio" name="turno" value="{{ $valor }}" required class="turno-radio sr-only">
                                <span class="inline-block cursor-pointer rounded-full border border-secondary/40 bg-surface px-3 py-1.5 text-sm text-on-surface-strong transition hover:border-secondary">
                                    {{ ucfirst(\Carbon\Carbon::parse($alternativa['fecha'])->translatedFormat('D d/m')) }} · {{ date('g:i A', strtotime($alternativa['inicio'])) }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

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

                {{-- El botón queda visible aunque haya muchos días con turnos --}}
                <div class="sticky bottom-0 mt-6 border-t border-outline bg-surface py-4">
                    <x-ui.boton type="submit" tamano="lg">{{ $textoBoton }}</x-ui.boton>
                </div>
            @endif
        </form>
    @endif
</x-ui.tarjeta>
