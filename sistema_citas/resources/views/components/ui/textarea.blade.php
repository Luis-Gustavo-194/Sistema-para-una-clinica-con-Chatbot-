{{-- Área de texto de Penguin UI con etiqueta, ayuda y error de validación. --}}
@props(['nombre', 'etiqueta' => null, 'valor' => null, 'ayuda' => null, 'id' => null, 'filas' => 4])

@php
    $id = $id ?? $nombre;
    $error = $errors->first($nombre);
@endphp

<div class="flex w-full flex-col gap-1.5">
    @if ($etiqueta)
        <label for="{{ $id }}" class="w-fit pl-0.5 text-sm font-semibold text-on-surface-strong">{{ $etiqueta }}</label>
    @endif
    <textarea id="{{ $id }}" name="{{ $nombre }}" rows="{{ $filas }}"
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->class([
            'w-full rounded-radius border bg-surface-alt px-3 py-2 text-sm text-on-surface-strong placeholder:text-on-surface/60',
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
            'border-danger' => $error,
            'border-outline' => !$error,
        ]) }}>{{ old($nombre, $valor) }}</textarea>
    @if ($error)
        <small id="{{ $id }}-error" class="pl-0.5 text-xs font-medium text-danger">{{ $error }}</small>
    @elseif ($ayuda)
        <small class="pl-0.5 text-xs text-on-surface">{{ $ayuda }}</small>
    @endif
</div>
