{{-- Módulo de recepción: solo define el menú; el diseño está en layouts/panel.
     Lo usan la Recepcionista y el Administrador (que además ve su propio menú). --}}
@php
    $esAdmin = (int) Auth::user()->rol_id === \App\Models\Rol::ADMINISTRADOR;
    $menu = [
        ['ruta' => 'recepcion.pacientes.index', 'activo' => ['recepcion.pacientes.index', 'recepcion.pacientes.show', 'recepcion.pacientes.edit'], 'icono' => 'buscar', 'texto' => 'Buscar pacientes'],
        ['ruta' => 'recepcion.pacientes.create', 'activo' => 'recepcion.pacientes.create', 'icono' => 'mas', 'texto' => 'Registrar paciente'],
    ];
    if ($esAdmin) {
        $menu = array_merge([
            ['ruta' => 'admin.dashboard', 'activo' => 'admin.dashboard', 'icono' => 'grafico', 'texto' => 'Dashboard'],
            ['ruta' => 'admin.usuarios.index', 'activo' => 'admin.usuarios.*', 'icono' => 'usuarios', 'texto' => 'Usuarios'],
        ], $menu);
    }
    $rolNombre = $esAdmin ? 'Administrador' : 'Recepcionista';
    $usuarioNombre = Auth::user()->correo;
    $inicioUrl = $esAdmin ? route('admin.dashboard') : route('recepcion.pacientes.index');
    $saludoChat = null;
@endphp

@extends('layouts.panel')
