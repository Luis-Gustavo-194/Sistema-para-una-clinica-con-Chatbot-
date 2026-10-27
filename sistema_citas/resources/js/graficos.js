/*
 * Gráficos del dashboard del administrador (Chart.js).
 * Cada <canvas> declara su tipo y sus datos:
 *   <canvas data-grafico="por-dia" data-datos='{"etiquetas": [...], "valores": [...]}'>
 */
import Chart from 'chart.js/auto';

// Ejes y grilla discretos; colores de la paleta validada del dashboard
const color = { texto: '#52514e', tenue: '#898781', grilla: '#e1e0d9', eje: '#c3c2b7' };
const coloresEstado = {
    Pendiente: '#86b6ef',
    Confirmada: '#2a78d6',
    Atendida: '#0ca30c',
    'No Asistio': '#ec835a',
    Cancelada: '#d03b3b',
};

const ejeValores = { beginAtZero: true, ticks: { precision: 0 }, grid: { color: color.grilla }, border: { display: false } };
const ejeCategorias = { grid: { display: false }, border: { color: color.eje } };
const textoEstado = (estado) => (estado === 'No Asistio' ? 'No asistió' : estado);

const constructores = {
    // Citas por día: días pasados (azul) y programadas a futuro (azul claro)
    'por-dia': (datos) => ({
        type: 'bar',
        data: {
            labels: datos.etiquetas,
            datasets: [
                { label: 'Hasta hoy', data: datos.valores.map((v, i) => (datos.futuro[i] ? null : v)), backgroundColor: '#2a78d6' },
                { label: 'Programadas', data: datos.valores.map((v, i) => (datos.futuro[i] ? v : null)), backgroundColor: '#86b6ef' },
            ].map((ds) => ({ ...ds, borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'start', maxBarThickness: 18 })),
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { ...ejeCategorias, stacked: true, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                y: { ...ejeValores, stacked: true },
            },
            plugins: {
                legend: { position: 'top', align: 'end', labels: { boxWidth: 12, boxHeight: 12, color: color.texto } },
                tooltip: {
                    filter: (item) => item.raw !== null,
                    callbacks: { label: (item) => ` ${item.dataset.label}: ${item.raw} cita(s)` },
                },
            },
        },
    }),

    // Citas por estado: colores de estado; el nombre va en el eje (no depende solo del color)
    'por-estado': (datos) => barrasHorizontales(
        datos.etiquetas.map(textoEstado),
        datos.valores,
        datos.etiquetas.map((estado) => coloresEstado[estado]),
    ),

    // Citas por especialidad: una sola serie
    'por-especialidad': (datos) => barrasHorizontales(datos.etiquetas, datos.valores, '#2a78d6'),
};

function barrasHorizontales(etiquetas, valores, colores) {
    return {
        type: 'bar',
        data: {
            labels: etiquetas,
            datasets: [{
                label: 'Citas',
                data: valores,
                backgroundColor: colores,
                borderRadius: { topRight: 4, bottomRight: 4 },
                borderSkipped: 'start',
                maxBarThickness: 26,
            }],
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            scales: { x: ejeValores, y: { ...ejeCategorias, ticks: { color: color.texto } } },
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (item) => ` ${item.raw} cita(s)` } } },
        },
    };
}

export function iniciarGraficos() {
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = color.tenue;
    Chart.defaults.plugins.tooltip.backgroundColor = '#0b0b0b';
    Chart.defaults.plugins.tooltip.padding = 10;

    document.querySelectorAll('canvas[data-grafico]').forEach((lienzo) => {
        const construir = constructores[lienzo.dataset.grafico];
        if (construir) {
            new Chart(lienzo, construir(JSON.parse(lienzo.dataset.datos)));
        }
    });
}
