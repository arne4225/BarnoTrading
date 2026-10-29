import { createChart, AreaSeries, HistogramSeries } from 'lightweight-charts';

/**
 * Equity curve (lijn/vlak) met daaronder de drawdown in % t.o.v. de eerdere piek.
 * De punten komen kant-en-klaar uit de StatisticsService.
 */
export default (points) => ({
    init() {
        if (points.length < 2) return;

        const chart = createChart(this.$refs.chart, {
            height: 360,
            layout: { background: { color: 'transparent' }, textColor: '#94a3b8', panes: { separatorColor: '#1e293b' } },
            grid: { vertLines: { color: '#1e293b' }, horzLines: { color: '#1e293b' } },
            rightPriceScale: { borderColor: '#334155' },
            timeScale: { borderColor: '#334155' },
        });

        const equity = chart.addSeries(AreaSeries, {
            lineColor: '#34d399', topColor: 'rgba(52,211,153,0.35)', bottomColor: 'rgba(52,211,153,0)', title: 'Equity',
        });
        equity.setData(points.map((p) => ({ time: p.date, value: p.equity_cents / 100 })));

        const drawdown = chart.addSeries(HistogramSeries, { color: 'rgba(248,113,113,0.6)', title: 'Drawdown %' }, 1);
        drawdown.setData(points.map((p) => ({ time: p.date, value: p.drawdown_percent })));

        chart.timeScale().fitContent();
        new ResizeObserver(() => chart.applyOptions({ width: this.$refs.chart.clientWidth })).observe(this.$refs.chart);
    },
});
