import Alpine from 'alpinejs';
import equityChart from './equity-chart';
import stockChart from './stock-chart';

window.Alpine = Alpine;

Alpine.data('stockChart', stockChart);
Alpine.data('equityChart', equityChart);

Alpine.start();
