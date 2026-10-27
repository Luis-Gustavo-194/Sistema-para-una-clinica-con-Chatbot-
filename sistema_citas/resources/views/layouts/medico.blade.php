{{-- Panel del médico: solo define el menú; el diseño está en layouts/panel --}}
@php
    $perfilMedico = \App\Models\Medico::with('especialidad')->where('usuario_id', Auth::id())->first();
    $menu = [
        [
            'ruta' => 'medico.panel', 'activo' => 'medico.panel', 'icono' => 'calendario', 'texto' => 'Agenda de hoy',
            'contador' => $perfilMedico ? \App\Models\Cita::noCanceladas()->where('medico_id', $perfilMedico->id)->where('fecha', today()->toDateString())->count() : 0,
            'contadorTitulo' => 'Citas de hoy',
        ],
        ['ruta' => 'medico.citas.index', 'activo' => 'medico.citas.*', 'icono' => 'documento', 'texto' => 'Mis citas'],
        ['ruta' => 'medico.horarios.edit', 'activo' => 'medico.horarios.*', 'icono' => 'reloj', 'texto' => 'Mis horarios'],
    ];
    $rolNombre = 'Médico · ' . ($perfilMedico->especialidad->nombre ?? '');
    $usuarioNombre = 'Dr(a). ' . ($perfilMedico->nombres ?? '') . ' ' . ($perfilMedico->apellidos ?? '');
    $iniciales = mb_strtoupper(mb_substr($perfilMedico->nombres ?? '', 0, 1) . mb_substr($perfilMedico->apellidos ?? '', 0, 1));
    $inicioUrl = route('medico.panel');
    $saludoChat = '¡Hola Dr(a). ' . ($perfilMedico->apellidos ?? '') . '! Soy su asistente virtual. Puede preguntarme por su agenda o sus citas pendientes.';
    $placeholderChat = 'Consulte su agenda...';
@endphp

@extends('layouts.panel')
