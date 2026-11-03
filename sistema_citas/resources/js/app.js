/*
 * Punto de entrada del JavaScript (lo compila Vite).
 * Las vistas no llevan <script> ni onclick: el comportamiento vive en estos módulos
 * y se conecta con atributos de Alpine.js (x-data, @click, ...).
 */
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

import { chatbot } from './chatbot';
import { carrusel } from './carrusel';
import { registrarConfirmaciones } from './confirmar';
import { validacionEnLinea } from './validacion';

Alpine.plugin(focus);
Alpine.plugin(collapse);

Alpine.data('chatbot', chatbot);
Alpine.data('carrusel', carrusel);
Alpine.data('validacionEnLinea', validacionEnLinea);
registrarConfirmaciones(Alpine);

window.Alpine = Alpine;
Alpine.start();

// Chart.js solo se descarga en las páginas que tienen gráficos
if (document.querySelector('[data-grafico]')) {
    import('./graficos').then(({ iniciarGraficos }) => iniciarGraficos());
}
