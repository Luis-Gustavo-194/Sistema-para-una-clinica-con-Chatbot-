{{-- Modal de confirmación (Penguin UI). Lo abre resources/js/confirmar.js para los <form data-confirmar="..."> --}}
<div x-data x-cloak x-show="$store.confirmar.abierto"
     x-transition.opacity.duration.200ms
     x-trap.inert.noscroll="$store.confirmar.abierto"
     x-on:keydown.esc.window="$store.confirmar.cerrar()"
     x-on:click.self="$store.confirmar.cerrar()"
     class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 backdrop-blur-sm sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="modal-confirmar-titulo">
    <div x-show="$store.confirmar.abierto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100"
         class="flex w-full max-w-md flex-col overflow-hidden rounded-radius bg-surface text-on-surface shadow-xl ring-1 ring-outline">
        <div class="flex items-center justify-between border-b border-outline bg-surface-alt p-4">
            <h3 id="modal-confirmar-titulo" class="font-semibold text-on-surface-strong">Confirmar acción</h3>
            <button type="button" x-on:click="$store.confirmar.cerrar()" aria-label="Cerrar">
                <x-icono nombre="cerrar" class="size-5" />
            </button>
        </div>
        <p class="px-4 py-6 text-sm" x-text="$store.confirmar.mensaje"></p>
        <div class="flex flex-col-reverse justify-end gap-2 border-t border-outline bg-surface-alt p-4 sm:flex-row">
            <x-ui.boton variante="secundario" x-on:click="$store.confirmar.cerrar()">Volver</x-ui.boton>
            <x-ui.boton variante="primario" x-on:click="$store.confirmar.aceptar()">Sí, continuar</x-ui.boton>
        </div>
    </div>
</div>
