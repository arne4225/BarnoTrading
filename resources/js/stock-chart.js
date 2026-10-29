import {
    createChart,
    CandlestickSeries,
    LineSeries,
    HistogramSeries,
    LineStyle,
    CrosshairMode,
} from 'lightweight-charts';

const OVERLAY_COLORS = ['#38bdf8', '#f59e0b', '#a78bfa', '#f472b6'];

/**
 * Alpine-component voor de candlestick-grafiek. Candles en indicatoren komen
 * kant-en-klaar uit de IndicatorService (PHP); dit bestand tekent alleen.
 */
export default (config) => ({
    timeframe: config.timeframe,
    timeframes: config.timeframes,
    indicators: {
        sma: { on: false, periods: '20,50' },
        ema: { on: false, periods: '9,21' },
        vwap: { on: false },
        volume: { on: true },
        rsi: { on: false, period: 14 },
        macd: { on: false, params: '12,26,9' },
    },
    levels: { stop_loss: null, take_profit: null },
    error: null,
    loading: false,
    chart: null,
    candleSeries: null,
    extraSeries: [],
    priceLines: {},
    dragging: null,
    timer: null,

    init() {
        this.chart = createChart(this.$refs.chart, {
            height: 420,
            layout: { background: { color: 'transparent' }, textColor: '#94a3b8', panes: { separatorColor: '#1e293b' } },
            grid: { vertLines: { color: '#1e293b' }, horzLines: { color: '#1e293b' } },
            crosshair: { mode: CrosshairMode.Normal },
            rightPriceScale: { borderColor: '#334155' },
            timeScale: { borderColor: '#334155', timeVisible: true, secondsVisible: false },
        });
        new ResizeObserver(() => this.chart.applyOptions({ width: this.$refs.chart.clientWidth })).observe(this.$refs.chart);

        this.bindDragging();
        this.load(true);
        this.timer = setInterval(() => this.load(false), 15000);
    },

    destroy() {
        clearInterval(this.timer);
        this.chart?.remove();
    },

    query() {
        const i = this.indicators;
        const params = new URLSearchParams({ tf: this.timeframe });
        if (i.sma.on) params.set('sma', i.sma.periods);
        if (i.ema.on) params.set('ema', i.ema.periods);
        if (i.vwap.on) params.set('vwap', 1);
        if (i.volume.on) params.set('volume', 1);
        if (i.rsi.on) params.set('rsi', i.rsi.period);
        if (i.macd.on) params.set('macd', i.macd.params);
        return params.toString();
    },

    async load(fit) {
        this.loading = true;
        try {
            const response = await fetch(`${config.url}?${this.query()}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Grafiekdata niet beschikbaar');
            this.render(await response.json(), fit);
        } catch (e) {
            this.error = e.message;
        } finally {
            this.loading = false;
        }
    },

    setTimeframe(value) {
        this.timeframe = value;
        this.load(true);
    },

    render(data, fit) {
        this.error = data.error;
        const visible = this.chart.timeScale().getVisibleLogicalRange();

        this.extraSeries.forEach((s) => this.chart.removeSeries(s));
        this.extraSeries = [];
        if (this.candleSeries) {
            this.chart.removeSeries(this.candleSeries);
            this.priceLines = {};
        }

        this.candleSeries = this.chart.addSeries(CandlestickSeries, {
            upColor: '#34d399', downColor: '#f87171', borderVisible: false,
            wickUpColor: '#34d399', wickDownColor: '#f87171',
        });
        this.candleSeries.setData(data.candles);
        this.restoreLevels();

        let colorIndex = 0;
        let pane = 1;
        for (const [name, series] of Object.entries(data.indicators)) {
            if (name === 'volume') {
                const volume = this.chart.addSeries(HistogramSeries, { priceFormat: { type: 'volume' }, priceScaleId: 'volume' });
                volume.priceScale().applyOptions({ scaleMargins: { top: 0.8, bottom: 0 } });
                volume.setData(series);
                this.extraSeries.push(volume);
            } else if (name === 'rsi') {
                const rsi = this.chart.addSeries(LineSeries, { color: '#fbbf24', lineWidth: 2, title: 'RSI' }, pane);
                rsi.setData(series);
                rsi.createPriceLine({ price: 70, color: '#64748b', lineStyle: LineStyle.Dashed, axisLabelVisible: false });
                rsi.createPriceLine({ price: 30, color: '#64748b', lineStyle: LineStyle.Dashed, axisLabelVisible: false });
                this.extraSeries.push(rsi);
                pane++;
            } else if (name === 'macd') {
                const histogram = this.chart.addSeries(HistogramSeries, { title: 'Hist' }, pane);
                const macd = this.chart.addSeries(LineSeries, { color: '#38bdf8', lineWidth: 2, title: 'MACD' }, pane);
                const signal = this.chart.addSeries(LineSeries, { color: '#f97316', lineWidth: 1, title: 'Signal' }, pane);
                histogram.setData(series.histogram);
                macd.setData(series.macd);
                signal.setData(series.signal);
                this.extraSeries.push(histogram, macd, signal);
                pane++;
            } else {
                const line = this.chart.addSeries(LineSeries, {
                    color: name === 'vwap' ? '#e879f9' : OVERLAY_COLORS[colorIndex++ % OVERLAY_COLORS.length],
                    lineWidth: 1, title: name.toUpperCase(), priceLineVisible: false, lastValueVisible: false,
                });
                line.setData(series);
                this.extraSeries.push(line);
            }
        }

        if (fit || !visible) this.chart.timeScale().fitContent();
        else this.chart.timeScale().setVisibleLogicalRange(visible);

        this.lastPrice = data.last_price;
    },

    toggle() {
        this.load(false);
    },

    /* --- Stop-loss en take-profit als sleepbare lijnen --- */

    addLevel(kind) {
        if (!this.lastPrice) return;
        const sign = (config.isShort ? -1 : 1) * (kind === 'stop_loss' ? -1 : 1);
        this.levels[kind] = Math.round(this.lastPrice * (1 + sign * (kind === 'stop_loss' ? 0.01 : 0.02)) * 100) / 100;
        this.drawLevel(kind);
        this.emitLevels();
    },

    removeLevel(kind) {
        this.levels[kind] = null;
        if (this.priceLines[kind]) {
            this.candleSeries.removePriceLine(this.priceLines[kind]);
            delete this.priceLines[kind];
        }
        this.emitLevels();
    },

    drawLevel(kind) {
        if (this.priceLines[kind]) this.candleSeries.removePriceLine(this.priceLines[kind]);
        this.priceLines[kind] = this.candleSeries.createPriceLine({
            price: this.levels[kind],
            color: kind === 'stop_loss' ? '#f87171' : '#34d399',
            lineWidth: 2,
            lineStyle: LineStyle.Dashed,
            axisLabelVisible: true,
            title: kind === 'stop_loss' ? 'SL (sleep)' : 'TP (sleep)',
        });
    },

    restoreLevels() {
        Object.keys(this.levels).forEach((kind) => this.levels[kind] !== null && this.drawLevel(kind));
    },

    emitLevels() {
        window.dispatchEvent(new CustomEvent('chart-levels', { detail: { ...this.levels } }));
    },

    bindDragging() {
        const el = this.$refs.chart;
        const hit = (event) => {
            const y = event.clientY - el.getBoundingClientRect().top;
            return Object.keys(this.levels).find((kind) => {
                if (this.levels[kind] === null) return false;
                const lineY = this.candleSeries.priceToCoordinate(this.levels[kind]);
                return lineY !== null && Math.abs(lineY - y) <= 6;
            });
        };

        el.addEventListener('mousedown', (event) => {
            const kind = hit(event);
            if (!kind) return;
            this.dragging = kind;
            this.chart.applyOptions({ handleScroll: false, handleScale: false });
            event.preventDefault();
        });
        window.addEventListener('mousemove', (event) => {
            if (!this.dragging) {
                el.style.cursor = hit(event) ? 'ns-resize' : '';
                return;
            }
            const price = this.candleSeries.coordinateToPrice(event.clientY - el.getBoundingClientRect().top);
            if (price === null) return;
            this.levels[this.dragging] = Math.round(price * 100) / 100;
            this.drawLevel(this.dragging);
        });
        window.addEventListener('mouseup', () => {
            if (!this.dragging) return;
            this.dragging = null;
            this.chart.applyOptions({ handleScroll: true, handleScale: true });
            this.emitLevels();
        });
    },
});
