@extends('layouts.paciente')

@section('titulo', 'Mi historia clínica')
@section('subtitulo', 'Diagnósticos, tratamientos y recetas registrados por tus médicos.')

@section('contenido')
    <div class="space-y-4">
        @forelse ($historias as $historia)
            <x-ui.tarjeta>
                <header class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-outline pb-3">
                    <h2 class="font-semibold text-primary">{{ ucfirst(\Carbon\Carbon::parse($historia->cita->fecha)->translatedFormat('d \d\e F \d\e Y')) }}</h2>
                    <span class="text-sm">Dr(a). {{ $historia->cita->medico->nombres ?? '' }} {{ $historia->cita->medico->apellidos ?? '' }} · {{ $historia->cita->medico->especialidad->nombre ?? '' }}</span>
                </header>
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[9rem_1fr] [&_dd]:whitespace-pre-wrap [&_dd]:text-on-surface-strong [&_dt]:font-medium">
                    <dt>Diagnóstico</dt><dd>{{ $historia->diagnostico }}</dd>
                    @if ($historia->tratamiento)<dt>Tratamiento</dt><dd>{{ $historia->tratamiento }}</dd>@endif
                    @if ($historia->receta_medica)<dt>Receta</dt><dd>{{ $historia->receta_medica }}</dd>@endif
                    @if ($historia->observaciones)<dt>Observaciones</dt><dd>{{ $historia->observaciones }}</dd>@endif
                </dl>
            </x-ui.tarjeta>
        @empty
            <x-ui.tarjeta class="text-center">
                <p class="text-on-surface">Aún no tienes atenciones registradas. Aparecerán aquí después de cada consulta.</p>
            </x-ui.tarjeta>
        @endforelse
    </div>
@endsection
