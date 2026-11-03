{{-- Casilla de verificación con su texto (en el slot) y el error de validación. --}}
@props(['nombre', 'valor' => '1', 'marcada' => false, 'id' => null])

@php
    $id = $id ?? str_replace(['[', ']', '.'], '_', $nombre);
    $error = $errors->first($nombre);
@endphp

<div class="flex w-full flex-col gap-1.5" data-campo>
    <label for="{{ $id }}" class="flex items-start gap-3 text-sm text-on-surface">
        <input id="{{ $id }}" name="{{ $nombre }}" type="checkbox" value="{{ $valor }}" @checked(old($nombre, $marcada))
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class([
                'mt-0.5 size-4 shrink-0 rounded border accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
                'border-danger' => $error,
                'border-outline' => !$error,
            ]) }}>
        <span>{{ $slot }}</span>
    </label>
    @if ($error)
        <small id="{{ $id }}-error" class="pl-7 text-xs font-medium text-danger">{{ $error }}</small>
    @endif
</div>
