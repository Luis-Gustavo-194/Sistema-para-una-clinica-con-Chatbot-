/*
 * Carrusel con cambio automático (componente Alpine), basado en el carrusel de Penguin UI.
 * Uso en Blade: <div x-data="carrusel(3)"> con diapositivas x-show="actual === i"
 */
export function carrusel(total, intervalo = 6000) {
    return {
        actual: 0,
        total,
        temporizador: null,

        init() {
            this.reanudar();
        },

        siguiente() {
            this.actual = (this.actual + 1) % this.total;
        },

        anterior() {
            this.actual = (this.actual - 1 + this.total) % this.total;
        },

        ir(indice) {
            this.actual = indice;
        },

        pausar() {
            clearInterval(this.temporizador);
        },

        // No se mueve solo si el usuario pidió reducir animaciones
        reanudar() {
            this.pausar();
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            this.temporizador = setInterval(() => this.siguiente(), intervalo);
        },
    };
}
