/*
 * Widget del asistente virtual (componente Alpine).
 * Uso en Blade: <div x-data="chatbot('Saludo inicial')"> ... (ver components/chatbot.blade.php)
 * Cualquier botón de la página puede abrirlo con: $dispatch('abrir-chat')
 */
function tokenCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function chatbot(saludo) {
    return {
        abierto: false,
        texto: '',
        enviando: false,
        // true mientras hay un flujo en curso (agendar, cancelar...): muestra los atajos Atrás / Cancelar
        enProceso: false,
        mensajes: [{ autor: 'bot', texto: saludo, opciones: [], enlace: null }],

        alternar() {
            this.abierto ? this.cerrar() : this.abrir();
        },

        abrir() {
            this.abierto = true;
            this.$nextTick(() => this.$refs.entrada?.focus());
        },

        cerrar() {
            this.abierto = false;
        },

        // Los botones de opciones muestran su "texto" pero envían su "valor"
        async enviar(textoVisible = null, valor = null) {
            const visible = (textoVisible ?? this.texto).trim();
            if (visible === '' || this.enviando) return;

            this.mensajes.push({ autor: 'usuario', texto: visible, opciones: [], enlace: null });
            this.texto = '';
            this.enviando = true;
            this.bajar();

            try {
                const respuesta = await fetch('/chatbot/enviar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': tokenCsrf(),
                    },
                    body: JSON.stringify({ mensaje: valor ?? visible }),
                });
                const datos = await respuesta.json();

                if (respuesta.ok) {
                    this.enProceso = Boolean(datos.en_proceso);
                    this.responder(datos.respuesta, datos.opciones ?? [], datos.enlace ?? null);
                } else if (respuesta.status === 429) {
                    this.responder('Estás enviando mensajes muy rápido. Espera un momento y vuelve a intentarlo.');
                } else {
                    this.responder('Error de conexión con el servidor.');
                }
            } catch {
                this.responder('No pude comunicarme con el servidor. Revisa tu conexión e inténtalo de nuevo.');
            } finally {
                this.enviando = false;
                this.bajar();
                this.$nextTick(() => this.$refs.entrada?.focus());
            }
        },

        responder(texto, opciones = [], enlace = null) {
            this.mensajes.push({ autor: 'bot', texto, opciones, enlace });
        },

        bajar() {
            this.$nextTick(() => {
                const contenedor = this.$refs.mensajes;
                if (contenedor) contenedor.scrollTop = contenedor.scrollHeight;
            });
        },
    };
}
