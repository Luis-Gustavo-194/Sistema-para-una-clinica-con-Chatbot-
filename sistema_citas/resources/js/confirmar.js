/*
 * Confirmación antes de enviar formularios peligrosos (eliminar, cancelar, desactivar...).
 * En Blade basta con: <form data-confirmar="¿Eliminar este médico?" ...>
 * Se muestra el modal de components/ui/modal-confirmar.blade.php en lugar de confirm().
 */
export function registrarConfirmaciones(Alpine) {
    Alpine.store('confirmar', {
        abierto: false,
        mensaje: '',
        formulario: null,

        pedir(formulario, mensaje) {
            this.formulario = formulario;
            this.mensaje = mensaje;
            this.abierto = true;
        },

        aceptar() {
            const formulario = this.formulario;
            this.cerrar();
            // submit() no vuelve a disparar el evento "submit", así que no se repite la pregunta
            formulario?.submit();
        },

        cerrar() {
            this.abierto = false;
            this.formulario = null;
        },
    });

    document.addEventListener(
        'submit',
        (evento) => {
            const formulario = evento.target;
            if (!(formulario instanceof HTMLFormElement) || !formulario.dataset.confirmar) return;

            evento.preventDefault();
            Alpine.store('confirmar').pedir(formulario, formulario.dataset.confirmar);
        },
        true,
    );
}
