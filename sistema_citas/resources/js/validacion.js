/*
 * Validación en línea de formularios (HU02).
 * Uso en Blade: <form x-data="validacionEnLinea" novalidate ...>
 *
 * Usa las reglas HTML de cada campo (required, pattern, minlength, maxlength, type="email")
 * y además data-coincide="otro_campo" para las confirmaciones de contraseña.
 * Valida al salir de cada campo y al enviar; el servidor vuelve a validar todo.
 */
const MENSAJES = {
    valueMissing: (campo) => (campo.type === 'checkbox' ? 'Debes marcar esta casilla para continuar.' : 'Este campo es obligatorio.'),
    typeMismatch: () => 'Escribe un correo electrónico válido.',
    patternMismatch: (campo) => campo.dataset.mensaje || 'El formato no es válido.',
    tooShort: (campo) => `Debe tener al menos ${campo.minLength} caracteres.`,
    tooLong: (campo) => `No debe superar los ${campo.maxLength} caracteres.`,
    rangeOverflow: () => 'La fecha debe ser anterior a hoy.',
    rangeUnderflow: () => 'La fecha no es válida.',
};

function mensajeDeError(campo) {
    const coincide = campo.dataset.coincide;
    if (coincide && campo.value !== campo.form.elements[coincide]?.value) {
        return 'Las contraseñas no coinciden.';
    }
    for (const [clave, mensaje] of Object.entries(MENSAJES)) {
        if (campo.validity[clave]) {
            return mensaje(campo);
        }
    }
    return '';
}

function mostrarError(campo, mensaje) {
    const contenedor = campo.closest('[data-campo]') ?? campo.parentElement;
    let aviso = contenedor.querySelector('[data-error-cliente]');

    if (!aviso) {
        aviso = document.createElement('small');
        aviso.dataset.errorCliente = '';
        aviso.id = `${campo.id || campo.name}-error-cliente`;
        aviso.className = 'pl-0.5 text-xs font-medium text-danger';
        contenedor.appendChild(aviso);
    }

    aviso.textContent = mensaje;
    aviso.hidden = mensaje === '';
    // Al aparecer el error del navegador se oculta la ayuda o el error que vino del servidor
    contenedor.querySelectorAll('small:not([data-error-cliente])').forEach((otro) => (otro.hidden = mensaje !== ''));

    campo.classList.toggle('border-danger', mensaje !== '');
    campo.classList.toggle('border-outline', mensaje === '');
    if (mensaje) {
        campo.setAttribute('aria-invalid', 'true');
        campo.setAttribute('aria-describedby', aviso.id);
    } else {
        campo.removeAttribute('aria-invalid');
    }
}

export function validacionEnLinea() {
    return {
        init() {
            const campos = () => [...this.$el.elements].filter((campo) => campo.willValidate);

            const validar = (campo) => {
                const mensaje = mensajeDeError(campo);
                mostrarError(campo, mensaje);
                return mensaje === '';
            };

            this.$el.addEventListener('focusout', (evento) => {
                if (evento.target.willValidate && evento.target.value !== '') {
                    validar(evento.target);
                }
            });

            // Si ya se mostró un error, se corrige mientras la persona escribe
            this.$el.addEventListener('input', (evento) => {
                const campo = evento.target;
                if (campo.getAttribute('aria-invalid') === 'true' || campo.type === 'checkbox') {
                    validar(campo);
                }
                // Al cambiar la contraseña se revisa otra vez su confirmación
                campos()
                    .filter((otro) => otro.dataset.coincide === campo.name && otro.value !== '')
                    .forEach(validar);
            });

            this.$el.addEventListener('submit', (evento) => {
                const invalidos = campos().filter((campo) => !validar(campo));
                if (invalidos.length > 0) {
                    evento.preventDefault();
                    invalidos[0].focus();
                }
            });
        },
    };
}
