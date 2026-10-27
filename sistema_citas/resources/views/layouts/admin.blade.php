{{-- Panel del administrador: solo define el menú; el diseño está en layouts/panel --}}
@php
    $menu = [
        ['ruta' => 'admin.dashboard', 'activo' => 'admin.dashboard', 'icono' => 'grafico', 'texto' => 'Dashboard'],
        ['ruta' => 'admin.especialidades.index', 'activo' => 'admin.especialidades.*', 'icono' => 'corazon', 'texto' => 'Especialidades'],
        [
            'ruta' => 'admin.medicos.index', 'activo' => 'admin.medicos.*', 'icono' => 'medico', 'texto' => 'Médicos',
            'contador' => \App\Models\Medico::doesntHave('horarios')->count(), 'contadorTitulo' => 'Médicos sin horarios configurados',
        ],
    ];
    $rolNombre = 'Administrador';
    $usuarioNombre = Auth::user()->correo;
    $inicioUrl = route('admin.dashboard');
    $saludoChat = null;
@endphp

@extends('layouts.panel')
