@extends('layouts.recepcion')

@section('titulo', 'Reservar cita')
@section('subtitulo', "Para {$paciente->nombres} {$paciente->apellidos} · DNI {$paciente->dni}. Elige la especialidad, el médico y un horario libre.")

@section('migas')
    <li><a href="{{ route('recepcion.pacientes.index') }}" class="font-medium text-on-surface-strong hover:text-primary">Pacientes</a></li>
    <li aria-hidden="true">/</li>
    <li><a href="{{ route('recepcion.pacientes.show', $paciente) }}" class="font-medium text-on-surface-strong hover:text-primary">Ficha</a></li>
    <li aria-hidden="true">/</li>
    <li aria-current="page">Reservar cita</li>
@endsection

@section('acciones')
    <x-ui.boton :href="route('recepcion.pacientes.show', $paciente)" variante="secundario"><x-icono nombre="chevron-izquierda" class="size-4" /> Volver a la ficha</x-ui.boton>
@endsection

@section('contenido')
    @include('partials.selector-turno', [
        'rutaGet' => route('recepcion.pacientes.citas.create', $paciente),
        'rutaPost' => route('recepcion.pacientes.citas.store', $paciente),
        'metodoPost' => null,
        'textoBoton' => 'Reservar cita',
    ])
@endsection
