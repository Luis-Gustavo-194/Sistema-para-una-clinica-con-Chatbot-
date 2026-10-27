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
        mensajes: [{ autor: 'bot', texto: saludo, opciones: [] }],

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

            this.mensajes.push({ autor: 'usuario', texto: visible, opciones: [] });
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

                this.mensajes.push(
                    respuesta.ok
                        ? { autor: 'bot', texto: datos.respuesta, opciones: datos.opciones ?? [] }
                        : { autor: 'bot', texto: 'Error de conexión con el servidor.', opciones: [] },
                );
            } catch {
                this.mensajes.push({ autor: 'bot', texto: 'El motor NLP está desconectado.', opciones: [] });
            } finally {
                this.enviando = false;
                this.bajar();
            }
        },

        bajar() {
            this.$nextTick(() => {
                const contenedor = this.$refs.mensajes;
                if (contenedor) contenedor.scrollTop = contenedor.scrollHeight;
            });
        },
    };
}
