{{-- Alerta en línea de Penguin UI (dentro del contenido de la página). --}}
@props(['tono' => 'info', 'titulo' => null])

@php
    $tonos = [
        'info' => ['border-info bg-info/10', 'text-info', 'info'],
        'exito' => ['border-success bg-success/10', 'text-success', 'check-circulo'],
        'aviso' => ['border-warning bg-warning/10', 'text-acento-texto', 'alerta'],
        'peligro' => ['border-danger bg-danger/10', 'text-danger', 'alerta'],
    ];
    [$caja, $colorIcono, $icono] = $tonos[$tono] ?? $tonos['info'];
@endphp

<div {{ $attributes->merge(['class' => "flex w-full items-start gap-3 rounded-radius border p-4 text-sm text-on-surface-strong {$caja}"]) }} role="alert">
    <x-icono :nombre="$icono" class="mt-0.5 size-5 shrink-0 {{ $colorIcono }}" />
    <div class="flex-1">
        @if ($titulo)
            <p class="font-semibold">{{ $titulo }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
