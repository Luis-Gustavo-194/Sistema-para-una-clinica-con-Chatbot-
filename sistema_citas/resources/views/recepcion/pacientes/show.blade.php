@extends('layouts.recepcion')

@section('titulo', "{$paciente->nombres} {$paciente->apellidos}")
@section('subtitulo', "Ficha del paciente · DNI {$paciente->dni}")

@section('migas')
    <li><a href="{{ route('recepcion.pacientes.index') }}" class="font-medium text-on-surface-strong hover:text-primary">Pacientes</a></li>
    <li aria-hidden="true">/</li>
    <li aria-current="page">Ficha</li>
@endsection

@section('acciones')
    <x-ui.boton :href="route('recepcion.pacientes.citas.create', $paciente)"><x-icono nombre="calendario-mas" class="size-4" /> Reservar cita</x-ui.boton>
    <x-ui.boton :href="route('recepcion.pacientes.edit', $paciente)" variante="secundario"><x-icono nombre="lapiz" class="size-4" /> Editar ficha</x-ui.boton>
@endsection

@section('contenido')
    <div class="grid max-w-5xl gap-5">
        <x-ui.tarjeta titulo="Datos personales">
            <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="text-xs uppercase tracking-wide">DNI</dt><dd class="mt-0.5 font-semibold text-on-surface-strong">{{ $paciente->dni }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide">Nombres</dt><dd class="mt-0.5 font-semibold text-on-surface-strong">{{ $paciente->nombres }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide">Apellidos</dt><dd class="mt-0.5 font-semibold text-on-surface-strong">{{ $paciente->apellidos }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide">Fecha de nacimiento</dt><dd class="mt-0.5 font-semibold text-on-surface-strong">{{ $paciente->fecha_nacimiento ? \Carbon\Carbon::parse($paciente->fecha_nacimiento)->format('d/m/Y') . ' (' . $paciente->edad . ' años)' : '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide">Teléfono</dt><dd class="mt-0.5 font-semibold text-on-surface-strong">{{ $paciente->telefono ?: '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide">Seguro</dt><dd class="mt-0.5 font-semibold text-on-surface-strong">{{ $paciente->seguro ?: '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs uppercase tracking-wide">Correo de acceso</dt><dd class="mt-0.5 font-semibold text-on-surface-strong">{{ $paciente->usuario->correo ?? '—' }}</dd></div>
                <div>
                    <dt class="text-xs uppercase tracking-wide">Consentimiento de datos (Ley 29733)</dt>
                    <dd class="mt-1">
                        @if ($paciente->consentimiento_datos_at)
                            <x-ui.insignia tono="exito">✓ Aceptado el {{ $paciente->consentimiento_datos_at->format('d/m/Y') }}</x-ui.insignia>
                        @else
                            <x-ui.insignia tono="aviso">Sin registrar</x-ui.insignia>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-ui.tarjeta>

        <x-ui.tarjeta titulo="Últimas citas" subtitulo="Las 10 más recientes.">
            @if ($citas->isEmpty())
                <p class="text-sm">El paciente aún no tiene citas registradas.</p>
            @else
                <x-ui.tabla class="shadow-none ring-0">
                    <thead><tr><th>Fecha</th><th>Hora</th><th>Médico</th><th>Especialidad</th><th>Canal</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                        @foreach ($citas as $cita)
                            <tr>
                                <td class="tabular-nums">{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</td>
                                <td class="tabular-nums">{{ substr($cita->hora_inicio, 0, 5) }}</td>
                                <td>Dr(a). {{ $cita->medico->nombres ?? '' }} {{ $cita->medico->apellidos ?? '' }}</td>
                                <td>{{ $cita->medico->especialidad->nombre ?? '—' }}</td>
                                <td>{{ \App\Models\Cita::CANALES[$cita->canal] ?? $cita->canal }}</td>
                                <td><x-estado-cita :cita="$cita" /></td>
                                <td>
                                    @if ($cita->esModificable())
                                        <form method="POST" action="{{ route('recepcion.citas.cancelar', $cita) }}"
                                              data-confirmar="{{ '¿Cancelar la cita del ' . \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') . ' a las ' . substr($cita->hora_inicio, 0, 5) . '? El horario quedará libre.' }}">
                                            @csrf @method('PATCH')
                                            <x-ui.boton type="submit" variante="peligro" tamano="sm">Cancelar</x-ui.boton>
                                        </form>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.tabla>
            @endif
        </x-ui.tarjeta>
    </div>
@endsection
