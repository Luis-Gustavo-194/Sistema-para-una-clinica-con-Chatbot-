{{-- Notificaciones flotantes (toast de Penguin UI) para los mensajes de la sesión. --}}
@php
    $mensajes = [];
    if (session('exito')) {
        $mensajes[] = ['tono' => 'exito', 'titulo' => 'Listo', 'texto' => session('exito')];
    }
    if (session('aviso')) {
        $mensajes[] = ['tono' => 'aviso', 'titulo' => 'Atención', 'texto' => session('aviso')];
    }
    $estilos = [
        'exito' => ['border-success', 'text-success', 'check-circulo'],
        'aviso' => ['border-warning', 'text-acento-texto', 'alerta'],
    ];
@endphp

<div class="pointer-events-none fixed inset-x-4 top-4 z-50 flex flex-col gap-3 sm:left-auto sm:right-6 sm:w-96" aria-live="polite">
    @foreach ($mensajes as $mensaje)
        @php [$borde, $color, $icono] = $estilos[$mensaje['tono']]; @endphp
        <div x-data="{ visible: true }" x-init="setTimeout(() => visible = false, 8000)" x-show="visible"
             x-transition:leave="transition duration-300 ease-in" x-transition:leave-end="translate-x-8 opacity-0"
             class="pointer-events-auto flex items-start gap-3 rounded-radius border-l-4 bg-surface p-4 shadow-lg ring-1 ring-outline/60 {{ $borde }}" role="status">
            <x-icono :nombre="$icono" class="mt-0.5 size-5 shrink-0 {{ $color }}" />
            <div class="flex-1 text-sm">
                <p class="font-semibold text-on-surface-strong">{{ $mensaje['titulo'] }}</p>
                <p class="mt-0.5 text-on-surface">{{ $mensaje['texto'] }}</p>
            </div>
            <button type="button" x-on:click="visible = false" class="text-on-surface hover:text-on-surface-strong" aria-label="Cerrar notificación">
                <x-icono nombre="cerrar" class="size-4" />
            </button>
        </div>
    @endforeach

    @if ($errors->any())
        <div x-data="{ visible: true }" x-show="visible"
             class="pointer-events-auto flex items-start gap-3 rounded-radius border-l-4 border-danger bg-surface p-4 shadow-lg ring-1 ring-outline/60" role="alert">
            <x-icono nombre="alerta" class="mt-0.5 size-5 shrink-0 text-danger" />
            <div class="flex-1 text-sm">
                <p class="font-semibold text-on-surface-strong">Revisa los datos</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-4 text-on-surface">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" x-on:click="visible = false" class="text-on-surface hover:text-on-surface-strong" aria-label="Cerrar">
                <x-icono nombre="cerrar" class="size-4" />
            </button>
        </div>
    @endif
</div>
