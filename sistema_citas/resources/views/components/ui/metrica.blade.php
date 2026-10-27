{{-- Tarjeta de métrica estilo Orchid: etiqueta gris, número grande y un dato de apoyo. --}}
@props(['titulo', 'valor', 'sufijo' => null, 'detalle' => null, 'tono' => 'neutro'])

@php
    $tonos = [
        'neutro' => 'text-on-surface',
        'exito' => 'text-success',
        'peligro' => 'text-danger',
        'aviso' => 'text-acento-texto',
        'info' => 'text-info',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-radius bg-surface p-5 shadow-sm ring-1 ring-outline/60']) }}>
    <p class="text-sm text-on-surface">{{ $titulo }}</p>
    <p class="mt-2 text-3xl font-semibold tracking-tight text-on-surface-strong">
        {{ $valor }}
        @if ($sufijo)
            <span class="text-base font-medium text-on-surface">{{ $sufijo }}</span>
        @endif
    </p>
    @if ($detalle || $slot->isNotEmpty())
        <p class="mt-1 text-xs font-medium {{ $tonos[$tono] ?? $tonos['neutro'] }}">{{ $detalle }}{{ $slot }}</p>
    @endif
</div>
