@extends('layouts.medico')

@section('titulo', 'Mis horarios de atención')
@section('subtitulo', 'Los pacientes y el chatbot solo ofrecerán turnos dentro de estos horarios. El segundo bloque es opcional (por ejemplo, un turno de tarde).')

@section('contenido')
    @include('partials.form-horarios', ['accion' => route('medico.horarios.update')])
@endsection
