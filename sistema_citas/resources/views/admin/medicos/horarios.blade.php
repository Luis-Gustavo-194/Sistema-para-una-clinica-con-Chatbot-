@extends('layouts.admin')

@section('titulo', 'Horarios de atención')
@section('subtitulo', "Dr(a). {$medico->nombres} {$medico->apellidos} · " . ($medico->especialidad->nombre ?? '') . '. Los pacientes, recepción y el chatbot solo ofrecerán turnos dentro de estos horarios.')

@section('migas')
    <li><a href="{{ route('admin.medicos.index') }}" class="font-medium text-on-surface-strong hover:text-primary">Médicos</a></li>
    <li aria-hidden="true">/</li>
    <li aria-current="page">Horarios</li>
@endsection

@section('contenido')
    @include('partials.form-horarios', ['accion' => route('admin.medicos.horarios.update', $medico)])
@endsection
