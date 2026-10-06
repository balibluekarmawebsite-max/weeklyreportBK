import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

// Shared defaults tuned to the BKDS editorial look.
Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#124E68';          // ink-600 for axis/legend text
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.legend.labels.boxHeight = 8;
Chart.defaults.plugins.legend.labels.padding = 16;
Chart.defaults.plugins.tooltip.backgroundColor = '#0A2B3A';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.maintainAspectRatio = false;

window.Chart = Chart;

// Format helpers available to inline chart scripts.
window.bkFmt = {
    idr: (v) => (v == null ? '–' : new Intl.NumberFormat('en-US').format(Math.round(v))),
    pct: (v, d = 1) => (v == null ? '–' : v.toFixed(d) + '%'),
};
