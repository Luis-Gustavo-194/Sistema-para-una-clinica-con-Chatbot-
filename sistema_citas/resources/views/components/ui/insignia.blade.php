{{-- Insignia de Penguin UI. Siempre lleva texto: el color nunca es la única pista. --}}
@props(['tono' => 'neutro'])

@php
    $tonos = [
        'neutro' => 'border-outline bg-surface-alt text-on-surface',
        'primario' => 'border-primary/30 bg-lavanda text-primary',
        'info' => 'border-info/30 bg-info/10 text-[#184f95]',
        'confirmada' => 'border-info bg-info text-on-info',
        'exito' => 'border-success/30 bg-success/10 text-[#006300]',
        'aviso' => 'border-secondary/40 bg-secondary/10 text-[#8a3a12]',
        'peligro' => 'border-danger/30 bg-danger/10 text-[#9b2222]',
        'sobre-oscuro' => 'border-white/40 bg-white/15 text-white',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex w-fit items-center gap-1 whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-semibold ' . ($tonos[$tono] ?? $tonos['neutro'])]) }}>{{ $slot }}</span>
