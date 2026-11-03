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
    @include('partials.selector-turno', [
        'rutaGet' => $reprogramando ? route('paciente.citas.edit', $cita) : route('paciente.citas.create'),
        'rutaPost' => $reprogramando ? route('paciente.citas.update', $cita) : route('paciente.citas.store'),
        'metodoPost' => $reprogramando ? 'PUT' : null,
        'textoBoton' => $reprogramando ? 'Confirmar nuevo horario' : 'Confirmar cita',
    ])
@endsection
