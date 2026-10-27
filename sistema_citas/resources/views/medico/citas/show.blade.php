@extends('layouts.medico')

@section('titulo', $cita->paciente->nombres . ' ' . $cita->paciente->apellidos)
@section('subtitulo', 'Cita del ' . \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') . ', ' . date('h:i A', strtotime($cita->hora_inicio)) . ' – ' . date('h:i A', strtotime($cita->hora_fin)))

@section('migas')
    <li><a href="{{ route('medico.citas.index') }}" class="font-medium text-on-surface-strong hover:text-primary">Mis citas</a></li>
    <li aria-hidden="true">/</li>
    <li aria-current="page">Detalle</li>
@endsection

@section('acciones')
    <x-estado-cita :cita="$cita" />
    <x-ui.boton :href="url()->previous() !== url()->current() ? url()->previous() : route('medico.panel')" variante="secundario">
        <x-icono nombre="chevron-izquierda" class="size-4" /> Volver
    </x-ui.boton>
@endsection

@section('contenido')
    <div class="grid items-start gap-5 xl:grid-cols-[22rem_1fr]">
        <x-ui.tarjeta titulo="Datos del paciente">
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm xl:grid-cols-1">
                <div><dt class="text-xs uppercase tracking-wide text-on-surface">DNI</dt><dd class="font-semibold text-on-surface-strong">{{ $cita->paciente->dni }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-on-surface">Edad</dt><dd class="font-semibold text-on-surface-strong">{{ $cita->paciente->edad !== null ? $cita->paciente->edad . ' años' : '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-on-surface">Fecha de nacimiento</dt><dd class="font-semibold text-on-surface-strong">{{ $cita->paciente->fecha_nacimiento ? \Carbon\Carbon::parse($cita->paciente->fecha_nacimiento)->format('d/m/Y') : '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-on-surface">Teléfono</dt><dd class="font-semibold text-on-surface-strong">{{ $cita->paciente->telefono ?: '—' }}</dd></div>
            </dl>

            <div class="mt-5 flex flex-col gap-2">
                @if (($cita->estado ?? 'Pendiente') === 'Pendiente' && $cita->fecha >= today()->toDateString())
                    <form method="POST" action="{{ route('medico.citas.estado', $cita) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="estado" value="Confirmada">
                        <x-ui.boton type="submit" variante="exito" class="w-full"><x-icono nombre="check" class="size-4" /> Confirmar cita</x-ui.boton>
                    </form>
                @endif
                @if (in_array($cita->estado ?? 'Pendiente', ['Pendiente', 'Confirmada'], true) && $cita->fecha <= today()->toDateString())
                    <form method="POST" action="{{ route('medico.citas.estado', $cita) }}" data-confirmar="¿Marcar que el paciente no asistió?">
                        @csrf @method('PATCH')
                        <input type="hidden" name="estado" value="No Asistio">
                        <x-ui.boton type="submit" variante="secundario" class="w-full"><x-icono nombre="cerrar" class="size-4" /> No asistió</x-ui.boton>
                    </form>
                @endif
            </div>
        </x-ui.tarjeta>

        <div class="space-y-5">
            <x-ui.tarjeta :titulo="$cita->historiaClinica ? 'Atención registrada' : 'Registrar atención'">
                @if ($puedeAtender)
                    <form method="POST" action="{{ route('medico.citas.atender', $cita) }}" class="space-y-4">
                        @csrf
                        <x-ui.textarea nombre="diagnostico" etiqueta="Diagnóstico *" :valor="$cita->historiaClinica->diagnostico ?? ''" filas="3" required />
                        <x-ui.textarea nombre="tratamiento" etiqueta="Tratamiento" :valor="$cita->historiaClinica->tratamiento ?? ''" filas="3" />
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.textarea nombre="receta_medica" etiqueta="Receta médica" :valor="$cita->historiaClinica->receta_medica ?? ''" />
                            <x-ui.textarea nombre="observaciones" etiqueta="Observaciones" :valor="$cita->historiaClinica->observaciones ?? ''" />
                        </div>
                        <x-ui.boton type="submit">{{ $cita->historiaClinica ? 'Guardar cambios' : 'Registrar atención y marcar «Atendida»' }}</x-ui.boton>
                    </form>
                @else
                    <p class="text-sm text-on-surface">
                        {{ $cita->estado === 'Cancelada' ? 'La cita fue cancelada.' : 'Podrás registrar la atención el día de la cita.' }}
                    </p>
                @endif
            </x-ui.tarjeta>

            <x-ui.tarjeta titulo="Historial del paciente en la clínica">
                @forelse ($historial as $anterior)
                    <article class="border-l-4 border-lavanda-fuerte py-1 pl-4 [&:not(:last-child)]:mb-5">
                        <h3 class="flex flex-wrap items-center gap-2 text-sm font-semibold text-on-surface-strong">
                            {{ \Carbon\Carbon::parse($anterior->fecha)->format('d/m/Y') }} ·
                            Dr(a). {{ $anterior->medico->apellidos ?? '—' }} ({{ $anterior->medico->especialidad->nombre ?? '—' }})
                            <x-estado-cita :cita="$anterior" />
                        </h3>
                        @if ($anterior->historiaClinica)
                            <dl class="mt-2 space-y-1 text-sm [&_dd]:whitespace-pre-wrap">
                                <div><dt class="inline font-semibold">Diagnóstico:</dt> <dd class="inline">{{ $anterior->historiaClinica->diagnostico }}</dd></div>
                                @if ($anterior->historiaClinica->tratamiento)<div><dt class="inline font-semibold">Tratamiento:</dt> <dd class="inline">{{ $anterior->historiaClinica->tratamiento }}</dd></div>@endif
                                @if ($anterior->historiaClinica->receta_medica)<div><dt class="inline font-semibold">Receta:</dt> <dd class="inline">{{ $anterior->historiaClinica->receta_medica }}</dd></div>@endif
                            </dl>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-on-surface">Es la primera cita de este paciente en la clínica.</p>
                @endforelse
            </x-ui.tarjeta>
        </div>
    </div>
@endsection
