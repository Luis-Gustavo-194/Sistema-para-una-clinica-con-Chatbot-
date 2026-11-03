{{-- Panel del administrador: solo define el menú; el diseño está en layouts/panel --}}
@php
    $menu = [
        ['ruta' => 'admin.dashboard', 'activo' => 'admin.dashboard', 'icono' => 'grafico', 'texto' => 'Dashboard'],
        ['ruta' => 'admin.especialidades.index', 'activo' => 'admin.especialidades.*', 'icono' => 'corazon', 'texto' => 'Especialidades'],
        [
            'ruta' => 'admin.medicos.index', 'activo' => 'admin.medicos.*', 'icono' => 'medico', 'texto' => 'Médicos',
            'contador' => \App\Models\Medico::doesntHave('horarios')->count(), 'contadorTitulo' => 'Médicos sin horarios configurados',
        ],
        ['ruta' => 'admin.usuarios.index', 'activo' => 'admin.usuarios.*', 'icono' => 'usuarios', 'texto' => 'Usuarios'],
        ['ruta' => 'recepcion.pacientes.index', 'activo' => 'recepcion.*', 'icono' => 'usuario', 'texto' => 'Pacientes'],
        [
            'ruta' => 'admin.auditoria.index', 'activo' => 'admin.auditoria.*', 'icono' => 'escudo', 'texto' => 'Auditoría',
            'contador' => \App\Models\Auditoria::fallosDeAcceso()->where('fecha_hora', '>=', now()->subDay())->count(), 'contadorTitulo' => 'Intentos de acceso fallidos en las últimas 24 h',
        ],
        ['ruta' => 'admin.clinica.edit', 'activo' => 'admin.clinica.*', 'icono' => 'inicio', 'texto' => 'Datos de la clínica'],
    ];
    $rolNombre = 'Administrador';
    $usuarioNombre = Auth::user()->correo;
    $inicioUrl = route('admin.dashboard');
    $saludoChat = null;
@endphp

@extends('layouts.panel')
