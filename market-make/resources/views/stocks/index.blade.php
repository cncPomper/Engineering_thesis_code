<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Market Visualization</title>
    <script src="https://unpkg.com/lightweight-charts@4.2.0/dist/lightweight-charts.standalone.production.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header nav a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            margin: 0 10px;
            font-weight: 600;
        }

        .header nav a:hover {
            color: white;
            text-decoration: underline;
        }

        .controls {
            background: #f8f9fa;
            padding: 20px 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            border-bottom: 1px solid #dee2e6;
        }

        .control-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .control-group label {
            font-weight: 600;
            color: #333;
        }

        .control-group input,
        .control-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .control-group button {
            padding: 8px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .control-group button:hover {
            background: #764ba2;
        }

        .indicator-toggles label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            user-select: none;
        }

        .timeframe-buttons {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        .timeframe-buttons button {
            padding: 8px 16px;
            background: #e9ecef;
            color: #333;
            border: 2px solid transparent;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .timeframe-buttons button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .content {
            padding: 30px;
        }

        .quick-analysis {
            display: flex;
            justify-content: space-around;
            gap: 10px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 18px 10px;
            margin-bottom: 25px;
            text-align: center;
        }

        .qa-item {
            flex: 1;
            cursor: help;
        }

        .qa-letter {
            font-size: 22px;
            font-weight: 800;
            color: #444;
            margin-bottom: 6px;
        }

        .qa-value {
            font-size: 16px;
            font-weight: 700;
        }

        .qa-value.green { color: #26a69a; }
        .qa-value.orange { color: #ff9800; }
        .qa-value.red { color: #ef5350; }
        .qa-value.muted { color: #aaa; }

        .chart-section {
            margin-bottom: 40px;
        }

        .chart-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }

        .chart-container {
            position: relative;
            height: 500px;
            margin-bottom: 30px;
            background: #131722;
            border-radius: 8px;
            overflow: hidden;
        }

        .chart-legend {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 10;
            font-size: 13px;
            color: #d1d4dc;
            background: rgba(19, 23, 34, 0.6);
            padding: 6px 10px;
            border-radius: 4px;
            pointer-events: none;
            line-height: 1.5;
        }

        .chart-legend .symbol-name {
            font-size: 15px;
            font-weight: 700;
        }

        .chart-legend .up { color: #26a69a; }
        .chart-legend .down { color: #ef5350; }
        .chart-legend .dc-upper { color: #2962ff; }
        .chart-legend .dc-lower { color: #ef5350; }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #f5c6cb;
        }

        .info-box {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #bee5eb;
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .timeframe-buttons {
                margin-left: 0;
            }

            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📈 Stock Market Visualization</h1>
            <p>Interactive candlestick chart with volume — powered by yfinance data</p>
            <nav>
                <a href="/stocks">📈 Chart</a>
                <a href="/screener">🔎 Screener</a>
            </nav>
        </div>

        <div class="controls">
            <div class="control-group">
                <label for="startDate">Start Date:</label>
                <input type="date" id="startDate">
            </div>

            <div class="control-group">
                <label for="endDate">End Date:</label>
                <input type="date" id="endDate">
            </div>

            <div class="control-group">
                <label for="symbol">Stock:</label>
                <select id="symbol"></select>
            </div>

            <div class="control-group indicator-toggles">
                <label><input type="checkbox" id="toggleDC" checked> Donchian Channel</label>
                <label><input type="checkbox" id="toggleVolume" checked> Volume</label>
            </div>

            <div class="timeframe-buttons">
                <button class="timeframe-btn active" data-timeframe="1D">Daily</button>
                <button class="timeframe-btn" data-timeframe="1W">Weekly</button>
                <button class="timeframe-btn" data-timeframe="1M">Monthly</button>
            </div>
        </div>

        <div class="content">
            <div id="error" class="error" style="display: none;"></div>
            <div id="loading" class="loading" style="display: none;">Loading data...</div>

            <div id="mainChart" style="display: none;">
                <div class="quick-analysis" id="quickAnalysis" style="display: none;">
                    <div class="qa-item" title="Growth: 5-year revenue growth (annualized, from financial statements)">
                        <div class="qa-letter">G</div>
                        <div class="qa-value" id="qaG">—</div>
                    </div>
                    <div class="qa-item" id="qaRItem" title="Risk: 5-year fundamental reliability checklist">
                        <div class="qa-letter">R</div>
                        <div class="qa-value" id="qaR">—</div>
                    </div>
                    <div class="qa-item" title="Outlook: 12-month price projection from the 6-month linear trend">
                        <div class="qa-letter">O</div>
                        <div class="qa-value" id="qaO">—</div>
                    </div>
                    <div class="qa-item" title="Margin of safety: distance from the 52-week high">
                        <div class="qa-letter">W</div>
                        <div class="qa-value" id="qaW">—</div>
                    </div>
                    <div class="qa-item" title="Trend strength: price vs 200-day EMA">
                        <div class="qa-letter">T</div>
                        <div class="qa-value" id="qaT">—</div>
                    </div>
                    <div class="qa-item" title="50-week EMA trend: price above (UP) or below (DOWN) its 50-week EMA">
                        <div class="qa-letter">H</div>
                        <div class="qa-value" id="qaH">—</div>
                    </div>
                </div>

                <div class="chart-section">
                    <div class="chart-title">
                        <span id="chartTitle">Daily Price Movement</span>
                        <span id="selectedRange" style="color: #999; font-size: 14px;"></span>
                    </div>
                    <div id="priceChart" class="chart-container"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentData = null;
        let currentTimeframe = '1D';
        let chart = null;
        let candleSeries = null;
        let volumeSeries = null;
        let dcUpperSeries = null;
        let dcLowerSeries = null;
        let refreshLegend = null;
        let visibleFrom = null;
        let visibleTo = null;

        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        const symbolSelect = document.getElementById('symbol');
        const timeframeButtons = document.querySelectorAll('.timeframe-btn');
        const errorDiv = document.getElementById('error');
        const loadingDiv = document.getElementById('loading');
        const mainChartDiv = document.getElementById('mainChart');
        const dcToggle = document.getElementById('toggleDC');
        const volumeToggle = document.getElementById('toggleVolume');

        // Show/hide indicator series in place, no refetch or chart rebuild needed
        function applyIndicatorVisibility() {
            if (dcUpperSeries) dcUpperSeries.applyOptions({ visible: dcToggle.checked });
            if (dcLowerSeries) dcLowerSeries.applyOptions({ visible: dcToggle.checked });
            if (volumeSeries) volumeSeries.applyOptions({ visible: volumeToggle.checked });
            if (refreshLegend) refreshLegend();
        }

        dcToggle.addEventListener('change', applyIndicatorVisibility);
        volumeToggle.addEventListener('change', applyIndicatorVisibility);

        // Event listeners
        startDateInput.addEventListener('change', fetchData);
        endDateInput.addEventListener('change', fetchData);
        symbolSelect.addEventListener('change', () => {
            setFullRangeForSymbol();
            fetchData();
        });

        timeframeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                timeframeButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentTimeframe = btn.dataset.timeframe;
                fetchData();  // Refetch data with new timeframe
            });
        });

        function formatDateForAPI(dateStr) {
            const [year, month, day] = dateStr.split('-');
            return `${day}.${month}.${year}`;
        }

        async function loadSymbols() {
            const response = await fetch('/api/stocks/symbols');

            if (!response.ok) {
                throw new Error('Failed to load stock symbols');
            }

            const symbols = await response.json();

            if (!symbols.length) {
                throw new Error('No stocks in the database. Run "php artisan stocks:fetch" first.');
            }

            symbolSelect.innerHTML = '';
            symbols.forEach(s => {
                if (s.name) companyNames[s.symbol] = s.name;
                symbolRanges[s.symbol] = {
                    from: String(s.first_date).slice(0, 10),
                    to: String(s.last_date).slice(0, 10),
                };
                const option = document.createElement('option');
                option.value = s.symbol;
                option.textContent = `${s.symbol} - ${getCompanyName(s.symbol)}` + (s.sector ? ` (${s.sector})` : '');
                symbolSelect.appendChild(option);
            });
        }

        async function fetchData() {
            loadingDiv.style.display = 'block';
            mainChartDiv.style.display = 'none';
            errorDiv.style.display = 'none';

            visibleFrom = startDateInput.value;
            visibleTo = endDateInput.value;

            const start = formatDateForAPI(indicatorWarmupStart(startDateInput.value, currentTimeframe));
            const end = formatDateForAPI(endDateInput.value);
            const symbol = symbolSelect.value;

            if (!symbol) {
                loadingDiv.style.display = 'none';
                return;
            }

            try {
                const response = await fetch(
                    `/api/stocks/range?start=${start}&end=${end}&timeframe=${currentTimeframe}&symbol=${symbol}`
                );

                if (!response.ok) {
                    throw new Error('Failed to fetch data');
                }

                currentData = await response.json();
                loadingDiv.style.display = 'none';

                if (!currentData.data || currentData.data.length === 0) {
                    errorDiv.style.display = 'block';
                    errorDiv.textContent = `No data for ${symbol} in the selected period (${currentData.start} to ${currentData.end}).`;
                    return;
                }

                mainChartDiv.style.display = 'block';

                renderQuickAnalysis(symbol);
                renderChart(currentData);
            } catch (error) {
                loadingDiv.style.display = 'none';
                errorDiv.style.display = 'block';
                errorDiv.textContent = 'Error: ' + error.message;
            }
        }

        // Convert "dd.mm.yyyy" (API format) to "yyyy-mm-dd" (Lightweight Charts format)
        function toChartTime(dmy) {
            const [day, month, year] = dmy.split('.');
            return `${year}-${month}-${day}`;
        }

        function formatVolume(v) {
            if (v >= 1e6) return (v / 1e6).toFixed(2) + 'M';
            if (v >= 1e3) return (v / 1e3).toFixed(1) + 'K';
            return String(v);
        }

        // Donchian Channel: highest high / lowest low over the last `period` bars
        // https://pl.tradingview.com/support/solutions/43000502253/
        const DC_UPPER_PERIOD = 20;
        const DC_LOWER_PERIOD = 10;

        // Calendar days of extra history to fetch before the visible range, so the
        // DC bands have a full lookback window from the first visible bar
        // (TradingView computes indicators over history outside the viewport too)
        const WARMUP_DAYS = { '1D': 60, '1W': 250, '1M': 800 };

        function indicatorWarmupStart(isoDate, timeframe) {
            const d = new Date(isoDate);
            d.setDate(d.getDate() - (WARMUP_DAYS[timeframe] || 60));
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function donchianUpper(bars, period) {
            const out = [];
            for (let i = period - 1; i < bars.length; i++) {
                let highest = -Infinity;
                for (let j = i - period + 1; j <= i; j++) {
                    highest = Math.max(highest, bars[j].high);
                }
                out.push({ time: bars[i].time, value: highest });
            }
            return out;
        }

        function donchianLower(bars, period) {
            const out = [];
            for (let i = period - 1; i < bars.length; i++) {
                let lowest = Infinity;
                for (let j = i - period + 1; j <= i; j++) {
                    lowest = Math.min(lowest, bars[j].low);
                }
                out.push({ time: bars[i].time, value: lowest });
            }
            return out;
        }

        function renderChart(data) {
            const container = document.getElementById('priceChart');

            if (chart) {
                chart.remove();
                chart = null;
            }
            container.innerHTML = '';

            const bars = data.data.map(d => ({
                time: toChartTime(d.date || d.start_date),
                open: d.open,
                high: d.high,
                low: d.low,
                close: d.close,
                volume: d.volume,
            }));

            chart = LightweightCharts.createChart(container, {
                autoSize: true,
                layout: {
                    background: { type: 'solid', color: '#131722' },
                    textColor: '#d1d4dc',
                },
                grid: {
                    vertLines: { color: '#1e222d' },
                    horzLines: { color: '#1e222d' },
                },
                crosshair: {
                    mode: LightweightCharts.CrosshairMode.Normal,
                },
                rightPriceScale: { borderColor: '#2a2e39' },
                timeScale: { borderColor: '#2a2e39' },
            });

            candleSeries = chart.addCandlestickSeries({
                upColor: '#26a69a',
                downColor: '#ef5350',
                borderUpColor: '#26a69a',
                borderDownColor: '#ef5350',
                wickUpColor: '#26a69a',
                wickDownColor: '#ef5350',
            });
            candleSeries.setData(bars);

            // Volume histogram in its own pane at the bottom (like TradingView)
            volumeSeries = chart.addHistogramSeries({
                priceFormat: { type: 'volume' },
                priceScaleId: 'volume',
            });
            chart.priceScale('volume').applyOptions({
                scaleMargins: { top: 0.8, bottom: 0 },
            });
            volumeSeries.setData(bars.map(b => ({
                time: b.time,
                value: b.volume,
                color: b.close >= b.open ? 'rgba(38, 166, 154, 0.5)' : 'rgba(239, 83, 80, 0.5)',
            })));

            // Donchian Channel: upper band (20) and lower band (10), step lines like TradingView
            const dcUpperData = donchianUpper(bars, DC_UPPER_PERIOD);
            const dcLowerData = donchianLower(bars, DC_LOWER_PERIOD);

            dcUpperSeries = chart.addLineSeries({
                color: '#2962ff',
                lineWidth: 1,
                priceLineVisible: false,
                lastValueVisible: false,
                crosshairMarkerVisible: false,
            });
            dcUpperSeries.setData(dcUpperData);

            dcLowerSeries = chart.addLineSeries({
                color: '#ef5350',
                lineWidth: 1,
                priceLineVisible: false,
                lastValueVisible: false,
                crosshairMarkerVisible: false,
            });
            dcLowerSeries.setData(dcLowerData);

            const dcUpperByTime = new Map(dcUpperData.map(p => [p.time, p.value]));
            const dcLowerByTime = new Map(dcLowerData.map(p => [p.time, p.value]));

            // Show only the user-selected window; the warm-up history stays scrollable to the left
            chart.timeScale().setVisibleRange({ from: visibleFrom, to: visibleTo });

            // OHLC legend that follows the crosshair (like the TradingView header)
            const legend = document.createElement('div');
            legend.className = 'chart-legend';
            container.appendChild(legend);

            const barsByTime = new Map(bars.map(b => [b.time, b]));

            let legendBar = null;

            const updateLegend = (bar) => {
                if (!bar) return;
                legendBar = bar;
                const change = bar.close - bar.open;
                const changePct = bar.open ? (change / bar.open) * 100 : 0;
                const cls = change >= 0 ? 'up' : 'down';
                const sign = change >= 0 ? '+' : '';
                const dcUpper = dcUpperByTime.get(bar.time);
                const dcLower = dcLowerByTime.get(bar.time);
                const volPart = volumeToggle.checked
                    ? `· Vol <span class="${cls}">${formatVolume(bar.volume)}</span>`
                    : '';
                const dcPart = dcToggle.checked
                    ? `<br>DC ${DC_UPPER_PERIOD} <span class="dc-upper">${dcUpper !== undefined ? dcUpper.toFixed(2) : '—'}</span>
                       DC ${DC_LOWER_PERIOD} <span class="dc-lower">${dcLower !== undefined ? dcLower.toFixed(2) : '—'}</span>`
                    : '';
                legend.innerHTML = `
                    <span class="symbol-name">${getCompanyName(data.symbol)} (${data.symbol})</span>
                    · ${getTimeframeLabel(currentTimeframe)}<br>
                    O <span class="${cls}">${bar.open.toFixed(2)}</span>
                    H <span class="${cls}">${bar.high.toFixed(2)}</span>
                    L <span class="${cls}">${bar.low.toFixed(2)}</span>
                    C <span class="${cls}">${bar.close.toFixed(2)}</span>
                    <span class="${cls}">${sign}${change.toFixed(2)} (${sign}${changePct.toFixed(2)}%)</span>
                    ${volPart}${dcPart}
                `;
            };

            refreshLegend = () => updateLegend(legendBar);
            updateLegend(bars[bars.length - 1]);
            applyIndicatorVisibility();

            chart.subscribeCrosshairMove(param => {
                const bar = param.time ? barsByTime.get(param.time) : null;
                updateLegend(bar || bars[bars.length - 1]);
            });

            document.getElementById('chartTitle').textContent =
                `${getCompanyName(data.symbol)} (${data.symbol}) - ${getTimeframeLabel(currentTimeframe)}`;
            document.getElementById('selectedRange').textContent =
                ` (${formatDateForAPI(visibleFrom)} to ${formatDateForAPI(visibleTo)})`;
        }

        function getTimeframeLabel(tf) {
            const labels = { '1D': 'Daily', '1W': 'Weekly', '1M': 'Monthly' };
            return labels[tf] || tf;
        }

        // Filled from /api/stocks/symbols (names come from yfinance via the companies table)
        const companyNames = {};

        // Full DB history per symbol ({ from, to }), filled from /api/stocks/symbols
        const symbolRanges = {};

        // Default the date inputs to the whole history stored in the DB for the selected symbol
        function setFullRangeForSymbol() {
            const range = symbolRanges[symbolSelect.value];
            if (!range) return;
            startDateInput.value = range.from;
            endDateInput.value = range.to;
        }

        function getCompanyName(symbol) {
            return companyNames[symbol] || symbol;
        }

        // Quick Analysis (GROWTH) metrics per symbol, filled from /api/screener
        const quickMetrics = {};

        async function loadQuickAnalysis() {
            try {
                const response = await fetch('/api/screener');
                if (!response.ok) return;
                (await response.json()).forEach(m => { quickMetrics[m.symbol] = m; });
            } catch (e) {
                // Quick Analysis is optional decoration; the chart still works without it
            }
        }

        function qaSet(id, text, cls) {
            const el = document.getElementById(id);
            el.textContent = text;
            el.className = 'qa-value ' + cls;
        }

        function qaPct(v, greenAt, orangeAt) {
            if (v === null || v === undefined) return ['—', 'muted'];
            const cls = v >= greenAt ? 'green' : v >= orangeAt ? 'orange' : 'red';
            return [(v >= 0 ? '+' : '') + v.toFixed(1) + '%', cls];
        }

        function renderQuickAnalysis(symbol) {
            const box = document.getElementById('quickAnalysis');
            const m = quickMetrics[symbol];

            if (!m) {
                box.style.display = 'none';
                return;
            }
            box.style.display = 'flex';

            // G: 5Y revenue growth from fundamentals (run "php artisan stocks:fundamentals")
            qaSet('qaG', ...qaPct(m.revenue_growth, 10, 0));
            qaSet('qaO', ...qaPct(m.outlook, 10, 0));
            qaSet('qaW', ...qaPct(m.off_52w_high, -10, -25));
            qaSet('qaT', ...qaPct(m.vs_ema200, 0, 0));

            // R: fundamental reliability checklist, falling back to price-based checks
            if (m.reliability_max) {
                const ratio = m.reliability_score / m.reliability_max;
                qaSet('qaR', `${m.reliability_score}/${m.reliability_max}`,
                    ratio >= 0.8 ? 'green' : ratio >= 0.5 ? 'orange' : 'red');
                document.getElementById('qaRItem').title =
                    'Risk: 5-year fundamental reliability checklist\n' +
                    Object.entries(m.reliability_checks).map(([name, check]) =>
                        (check.passed ? '✓ ' : '✗ ') + name + ' — ' + check.details
                    ).join('\n');
            } else if (m.risk_max) {
                const ratio = m.risk_score / m.risk_max;
                qaSet('qaR', `${m.risk_score}/${m.risk_max}`,
                    ratio >= 0.8 ? 'green' : ratio >= 0.5 ? 'orange' : 'red');
                document.getElementById('qaRItem').title =
                    'Risk: price-based checks (no fundamentals fetched yet)\n' +
                    Object.entries(m.risk_checks).map(([name, passed]) =>
                        passed === null ? '– ' + name + ' (not enough history)' : (passed ? '✓ ' : '✗ ') + name
                    ).join('\n');
            } else {
                qaSet('qaR', '—', 'muted');
            }

            qaSet('qaH', m.ema_trend || '—',
                m.ema_trend === 'UP' ? 'green' : m.ema_trend === 'DOWN' ? 'red' : 'muted');
        }

        // Set default dates
        function setDefaultDates() {
            const today = new Date();
            const threeMonthsAgo = new Date(today.getFullYear(), today.getMonth() - 3, today.getDate());

            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            startDateInput.value = formatDate(threeMonthsAgo);
            endDateInput.value = formatDate(today);
        }

        // Fallback in case the symbol list has no range info
        setDefaultDates();

        // Initial load: populate the symbol list and GROWTH metrics, then chart the whole DB history
        Promise.all([loadSymbols(), loadQuickAnalysis()])
            .then(() => {
                setFullRangeForSymbol();
                fetchData();
            })
            .catch(error => {
                loadingDiv.style.display = 'none';
                errorDiv.style.display = 'block';
                errorDiv.textContent = 'Error: ' + error.message;
            });
    </script>
</body>
</html>
