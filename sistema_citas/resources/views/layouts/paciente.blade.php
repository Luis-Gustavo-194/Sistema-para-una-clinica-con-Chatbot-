{{-- Panel del paciente: solo define el menú; el diseño está en layouts/panel --}}
@php
    $perfilPaciente = \App\Models\Paciente::where('usuario_id', Auth::id())->first();
    $menu = [
        ['ruta' => 'paciente.panel', 'activo' => 'paciente.panel', 'icono' => 'inicio', 'texto' => 'Inicio'],
        [
            'ruta' => 'paciente.citas.index', 'activo' => ['paciente.citas.index', 'paciente.citas.edit'], 'icono' => 'calendario', 'texto' => 'Mis citas',
            'contador' => $perfilPaciente ? \App\Models\Cita::modificables()->where('paciente_id', $perfilPaciente->id)->count() : 0,
            'contadorTitulo' => 'Citas próximas',
        ],
        ['ruta' => 'paciente.citas.create', 'activo' => 'paciente.citas.create', 'icono' => 'calendario-mas', 'texto' => 'Agendar cita'],
        ['ruta' => 'paciente.historia', 'activo' => 'paciente.historia', 'icono' => 'documento', 'texto' => 'Mi historia clínica'],
        ['ruta' => 'paciente.perfil', 'activo' => 'paciente.perfil*', 'icono' => 'usuario', 'texto' => 'Mi perfil'],
    ];
    $rolNombre = 'Paciente · DNI ' . ($perfilPaciente->dni ?? '');
    $usuarioNombre = ($perfilPaciente->nombres ?? '') . ' ' . ($perfilPaciente->apellidos ?? '');
    $inicioUrl = route('paciente.panel');
    $saludoChat = '¡Hola ' . ($perfilPaciente->nombres ?? '') . '! Puedo ayudarte a agendar, cancelar o reprogramar tus citas. Como ya iniciaste sesión, no necesito tu DNI.';
@endphp

@extends('layouts.panel')
