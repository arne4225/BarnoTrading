import Alpine from 'alpinejs';
import stockChart from './stock-chart';

window.Alpine = Alpine;

Alpine.data('stockChart', stockChart);

Alpine.start();
