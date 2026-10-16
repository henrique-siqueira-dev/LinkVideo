import { BarController, BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

/** Lê as cores dos papéis definidos em .viz-root (app.css). */
function token(el, name) {
    return getComputedStyle(el.closest('.viz-root') ?? document.documentElement).getPropertyValue(name).trim();
}

const money = (cents) => (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const integer = (n) => n.toLocaleString('pt-BR');

/**
 * Gráfico de barras de uma série só (Alpine: x-data="barChart({...})").
 * labels/values do servidor; money=true formata os valores em reais (centavos);
 * horizontal=true para rankings.
 */
export function barChart({ labels, values, money: isMoney = false, horizontal = false, seriesLabel = '' }) {
    return {
        chart: null,

        init() {
            const canvas = this.$refs.canvas;
            const fmt = isMoney ? money : integer;
            const ink = token(canvas, '--viz-ink-secondary');
            const muted = token(canvas, '--viz-muted');
            const grid = token(canvas, '--viz-grid');
            const axis = token(canvas, '--viz-axis');
            const valueAxis = horizontal ? 'x' : 'y';
            const categoryAxis = horizontal ? 'y' : 'x';

            this.chart = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: seriesLabel,
                        data: values,
                        backgroundColor: token(canvas, '--viz-series-1'),
                        hoverBackgroundColor: token(canvas, '--viz-series-1-hover'),
                        // ponta arredondada só no lado do valor; base reta no eixo
                        borderRadius: 4,
                        borderSkipped: 'start',
                        maxBarThickness: horizontal ? 18 : 22,
                        categoryPercentage: 0.8,
                        barPercentage: 0.9,
                    }],
                },
                options: {
                    indexAxis: horizontal ? 'y' : 'x',
                    maintainAspectRatio: false,
                    animation: { duration: 300 },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: token(canvas, '--viz-tooltip-bg'),
                            borderColor: axis,
                            borderWidth: 1,
                            titleColor: token(canvas, '--viz-ink'),
                            bodyColor: ink,
                            padding: 10,
                            displayColors: false,
                            callbacks: { label: (ctx) => `${seriesLabel}: ${fmt(ctx.parsed[valueAxis])}` },
                        },
                    },
                    scales: {
                        [categoryAxis]: {
                            grid: { display: false },
                            border: { color: axis },
                            ticks: { color: muted, font: { size: 11 }, maxRotation: 0, autoSkip: true, autoSkipPadding: 12 },
                        },
                        [valueAxis]: {
                            beginAtZero: true,
                            grid: { color: grid, drawTicks: false },
                            border: { display: false },
                            ticks: {
                                color: muted,
                                font: { size: 11 },
                                padding: 8,
                                maxTicksLimit: 5,
                                precision: 0,
                                callback: (v) => (isMoney ? money(v) : integer(v)),
                            },
                        },
                    },
                },
            });
        },

        destroy() {
            this.chart?.destroy();
        },
    };
}
