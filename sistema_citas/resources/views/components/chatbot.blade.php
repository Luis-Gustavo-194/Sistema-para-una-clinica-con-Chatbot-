{{--
    Asistente virtual flotante. La lógica está en resources/js/chatbot.js.
    Cualquier botón puede abrirlo con: x-on:click="$dispatch('abrir-chat')"
--}}
@props([
    'saludo' => '¡Hola! Soy el asistente virtual de la clínica. ¿En qué te puedo ayudar hoy? Puedes pedirme agendar, cancelar o reprogramar una cita.',
    'placeholder' => 'Escribe tu mensaje...',
])

<div x-data="chatbot(@js($saludo))" x-on:abrir-chat.window="abrir()" x-on:keydown.escape.window="cerrar()"
     class="fixed bottom-5 right-5 z-40 flex flex-col items-end gap-3">

    <section x-cloak x-show="abierto"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-end="translate-y-4 opacity-0"
             class="flex h-[32rem] max-h-[calc(100vh-7rem)] w-[23rem] max-w-[calc(100vw-2.5rem)] flex-col overflow-hidden rounded-2xl bg-surface shadow-2xl ring-1 ring-outline"
             role="dialog" aria-label="Asistente virtual">
        <header class="flex items-center gap-3 bg-primary px-4 py-3 text-on-primary">
            <span class="flex size-9 items-center justify-center rounded-full bg-white/15">
                <x-icono nombre="robot" class="size-5" />
            </span>
            <div class="flex-1">
                <p class="text-sm font-semibold">Asistente virtual</p>
                <p class="flex items-center gap-1.5 text-xs text-white/80"><span class="size-2 rounded-full bg-[#4ade80]"></span> En línea</p>
            </div>
            <button type="button" x-on:click="cerrar()" class="rounded-full p-1 hover:bg-white/10" aria-label="Cerrar asistente">
                <x-icono nombre="cerrar" class="size-5" />
            </button>
        </header>

        <div x-ref="mensajes" class="flex-1 space-y-3 overflow-y-auto bg-surface-alt p-4" aria-live="polite">
            <template x-for="(mensaje, indice) in mensajes" :key="indice">
                <div class="flex" :class="mensaje.autor === 'usuario' ? 'justify-end' : 'justify-start'">
                    <div class="chat-burbuja max-w-[85%] rounded-2xl px-4 py-2.5 text-sm"
                         :class="mensaje.autor === 'usuario'
                            ? 'rounded-br-sm bg-primary text-on-primary'
                            : 'rounded-bl-sm bg-surface text-on-surface-strong ring-1 ring-outline'">
                        <p x-text="mensaje.texto"></p>
                        <div x-show="mensaje.opciones.length" class="mt-3 flex flex-wrap gap-2">
                            <template x-for="opcion in mensaje.opciones" :key="opcion.valor">
                                <button type="button" x-on:click="enviar(opcion.texto, opcion.valor)" x-text="opcion.texto"
                                        class="rounded-full border border-primary/30 bg-lavanda px-3 py-1 text-xs font-semibold text-primary transition hover:bg-primary hover:text-on-primary"></button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
            <p x-show="enviando" class="text-xs text-on-surface">El asistente está escribiendo…</p>
        </div>

        <form x-on:submit.prevent="enviar()" class="flex items-center gap-2 border-t border-outline bg-surface p-3">
            <input x-ref="entrada" x-model="texto" type="text" placeholder="{{ $placeholder }}" aria-label="Mensaje para el asistente" autocomplete="off"
                   class="min-w-0 flex-1 rounded-full border border-outline bg-surface-alt px-4 py-2 text-sm text-on-surface-strong focus-visible:outline-2 focus-visible:outline-primary">
            <button type="submit" class="flex size-10 items-center justify-center rounded-full bg-primary text-on-primary hover:bg-primary-hover disabled:opacity-50" x-bind:disabled="enviando" aria-label="Enviar mensaje">
                <x-icono nombre="flecha-derecha" class="size-5" />
            </button>
        </form>
    </section>

    <button type="button" x-on:click="alternar()" x-bind:aria-expanded="abierto" aria-label="Abrir asistente virtual"
            class="flex size-16 items-center justify-center rounded-full bg-primary text-on-primary shadow-xl ring-4 ring-white transition hover:scale-105 hover:bg-primary-hover">
        <x-icono nombre="chat" class="size-8" x-show="!abierto" />
        <x-icono nombre="cerrar" class="size-7" x-cloak x-show="abierto" />
    </button>
</div>
