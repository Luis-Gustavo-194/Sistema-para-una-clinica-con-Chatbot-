{{-- Logo de la clínica (marca propia) con el nombre configurado en config/clinica.php --}}
@props(['claro' => false, 'compacto' => false])

@php
    [$primera, $resto] = array_pad(explode(' ', config('clinica.nombre'), 2), 2, '');
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg viewBox="0 0 48 48" class="{{ $compacto ? 'size-9' : 'size-11' }} shrink-0" aria-hidden="true">
        <circle cx="24" cy="24" r="22" fill="#4a1d6e" />
        <path d="M24 13v22M13 24h22" stroke="#ffffff" stroke-width="6" stroke-linecap="round" />
        <path d="M31 33c4.5-1.5 7.5-5 8.5-10" stroke="#f28c28" stroke-width="3.5" fill="none" stroke-linecap="round" />
    </svg>
    <span class="flex flex-col leading-none">
        <span class="text-sm font-semibold {{ $claro ? 'text-white/80' : 'text-primary' }}">{{ $primera }}</span>
        <span class="{{ $compacto ? 'text-lg' : 'text-xl' }} font-bold tracking-tight {{ $claro ? 'text-white' : 'text-primary' }}">{{ $resto }}</span>
        @unless ($compacto)
            <span class="mt-0.5 self-end text-[0.7rem] font-semibold {{ $claro ? 'text-white/70' : 'text-acento-texto' }}">{{ config('clinica.ciudad') }}</span>
        @endunless
    </span>
</span>
