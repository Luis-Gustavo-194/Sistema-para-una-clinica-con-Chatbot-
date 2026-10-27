{{-- Botón de Penguin UI. Con href se dibuja como enlace. --}}
@props(['variante' => 'primario', 'tamano' => 'md', 'href' => null, 'type' => 'button'])

@php
    $variantes = [
        'primario' => 'bg-primary border-primary text-on-primary hover:bg-primary-hover focus-visible:outline-primary',
        'secundario' => 'bg-surface border-outline text-on-surface-strong hover:bg-surface-alt focus-visible:outline-primary',
        'acento' => 'bg-secondary border-secondary text-on-secondary hover:brightness-95 focus-visible:outline-secondary',
        'peligro' => 'bg-danger border-danger text-on-danger hover:brightness-95 focus-visible:outline-danger',
        'exito' => 'bg-success border-success text-on-success hover:brightness-95 focus-visible:outline-success',
        'fantasma' => 'bg-transparent border-transparent text-primary hover:bg-lavanda focus-visible:outline-primary',
        'claro' => 'bg-white border-white text-primary hover:bg-lavanda focus-visible:outline-white',
        'contorno-claro' => 'bg-transparent border-white text-white hover:bg-white/10 focus-visible:outline-white',
    ];
    $tamanos = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-base',
    ];
    $clases = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-radius border font-semibold tracking-wide transition '
        . 'focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-40 '
        . ($variantes[$variante] ?? $variantes['primario']) . ' ' . ($tamanos[$tamano] ?? $tamanos['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $clases]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $clases]) }}>{{ $slot }}</button>
@endif
