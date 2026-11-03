{{-- Formulario semanal de horarios. Lo usan medico/horarios y admin/medicos/horarios.
     Recibe: $accion (URL del PUT) y $dias (HorarioService::datosFormulario). --}}
<form method="POST" action="{{ $accion }}">
    @csrf
    @method('PUT')

    <x-ui.tabla>
        <thead>
            <tr><th>Día</th><th>Bloque 1</th><th>Bloque 2 (opcional)</th><th>Duración (min)</th><th>Consultorio</th></tr>
        </thead>
        <tbody>
            @foreach ($dias as $numero => $dia)
                {{-- Tras un error de validación se respeta lo que el médico marcó (un checkbox desmarcado no se envía) --}}
                @php
                    $activo = session()->hasOldInput() ? (bool) old("dias.{$numero}.activo") : $dia['activo'];
                    $claseHora = 'w-28 rounded-radius border border-outline bg-surface-alt px-2 py-1.5 text-sm text-on-surface-strong focus-visible:outline-2 focus-visible:outline-primary';
                @endphp
                <tr x-data="{ activo: {{ $activo ? 'true' : 'false' }} }">
                    <td>
                        <label class="flex items-center gap-2 font-semibold text-on-surface-strong">
                            <input type="checkbox" name="dias[{{ $numero }}][activo]" value="1" x-model="activo" @checked($activo)
                                   class="size-4 rounded accent-primary">
                            {{ $dia['nombre'] }}
                        </label>
                    </td>
                    <td x-bind:class="activo ? '' : 'opacity-40'">
                        <div class="flex items-center gap-1.5">
                            <input type="time" name="dias[{{ $numero }}][inicio]" value="{{ old("dias.{$numero}.inicio", $dia['inicio']) }}" class="{{ $claseHora }}" aria-label="Inicio {{ $dia['nombre'] }}">
                            –
                            <input type="time" name="dias[{{ $numero }}][fin]" value="{{ old("dias.{$numero}.fin", $dia['fin']) }}" class="{{ $claseHora }}" aria-label="Fin {{ $dia['nombre'] }}">
                        </div>
                    </td>
                    <td x-bind:class="activo ? '' : 'opacity-40'">
                        <div class="flex items-center gap-1.5">
                            <input type="time" name="dias[{{ $numero }}][inicio2]" value="{{ old("dias.{$numero}.inicio2", $dia['inicio2']) }}" class="{{ $claseHora }}" aria-label="Inicio bloque 2 {{ $dia['nombre'] }}">
                            –
                            <input type="time" name="dias[{{ $numero }}][fin2]" value="{{ old("dias.{$numero}.fin2", $dia['fin2']) }}" class="{{ $claseHora }}" aria-label="Fin bloque 2 {{ $dia['nombre'] }}">
                        </div>
                    </td>
                    <td x-bind:class="activo ? '' : 'opacity-40'">
                        <input type="number" min="10" max="120" step="5" name="dias[{{ $numero }}][duracion]" value="{{ old("dias.{$numero}.duracion", $dia['duracion']) }}" class="{{ $claseHora }} w-20" aria-label="Duración {{ $dia['nombre'] }}">
                    </td>
                    <td x-bind:class="activo ? '' : 'opacity-40'">
                        <input type="text" maxlength="50" name="dias[{{ $numero }}][consultorio]" value="{{ old("dias.{$numero}.consultorio", $dia['consultorio']) }}" placeholder="C-101" class="{{ $claseHora }}" aria-label="Consultorio {{ $dia['nombre'] }}">
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.tabla>

    <div class="mt-5">
        <x-ui.boton type="submit">Guardar horarios</x-ui.boton>
    </div>
</form>
