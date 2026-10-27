{{-- Lista desplegable de Penguin UI. Las <option> van en el slot. --}}
@props(['nombre', 'etiqueta' => null, 'ayuda' => null, 'id' => null])

@php
    $id = $id ?? str_replace(['[', ']', '.'], '_', $nombre);
    $error = $errors->first($nombre);
@endphp

<div class="flex w-full flex-col gap-1.5">
    @if ($etiqueta)
        <label for="{{ $id }}" class="w-fit pl-0.5 text-sm font-semibold text-on-surface-strong">{{ $etiqueta }}</label>
    @endif
    <div class="relative">
        <x-icono nombre="chevron-abajo" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-on-surface" />
        <select id="{{ $id }}" name="{{ $nombre }}"
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class([
                'w-full appearance-none rounded-radius border bg-surface-alt py-2 pl-3 pr-9 text-sm text-on-surface-strong',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-70',
                'border-danger' => $error,
                'border-outline' => !$error,
            ]) }}>
            {{ $slot }}
        </select>
    </div>
    @if ($error)
        <small id="{{ $id }}-error" class="pl-0.5 text-xs font-medium text-danger">{{ $error }}</small>
    @elseif ($ayuda)
        <small class="pl-0.5 text-xs text-on-surface">{{ $ayuda }}</small>
    @endif
</div>
