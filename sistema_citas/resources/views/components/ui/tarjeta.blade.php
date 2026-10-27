{{-- Tarjeta blanca (estilo Orchid). Con "titulo" muestra una cabecera. --}}
@props(['titulo' => null, 'subtitulo' => null])

<section {{ $attributes->merge(['class' => 'rounded-radius bg-surface p-6 shadow-sm ring-1 ring-outline/60']) }}>
    @if ($titulo)
        <header class="mb-4">
            <h2 class="text-base font-semibold text-on-surface-strong">{{ $titulo }}</h2>
            @if ($subtitulo)
                <p class="mt-0.5 text-sm text-on-surface">{{ $subtitulo }}</p>
            @endif
        </header>
    @endif
    {{ $slot }}
</section>
