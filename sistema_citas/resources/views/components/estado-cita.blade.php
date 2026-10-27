{{-- Estado de una cita: color + icono + texto. Uso: <x-estado-cita :cita="$cita" /> --}}
@props(['cita'])

@php
    [$tono, $icono] = [
        'Pendiente' => ['info', '◷'],
        'Confirmada' => ['confirmada', '✓'],
        'Atendida' => ['exito', '✔'],
        'No Asistio' => ['aviso', '✕'],
        'Cancelada' => ['peligro', '⊘'],
    ][$cita->estado ?? 'Pendiente'] ?? ['info', '◷'];
@endphp

<x-ui.insignia :tono="$tono"><span aria-hidden="true">{{ $icono }}</span> {{ $cita->estado_texto }}</x-ui.insignia>
