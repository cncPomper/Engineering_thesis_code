<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Screener</title>
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
            width: 90px;
        }

        .control-group select {
            width: auto;
        }

        .preset-btn {
            padding: 8px 20px;
            background: #e9ecef;
            color: #333;
            border: 2px solid transparent;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s;
        }

        .preset-btn.active {
            background: #26a69a;
            color: white;
            border-color: #26a69a;
        }

        .reset-btn {
            padding: 8px 16px;
            background: transparent;
            color: #667eea;
            border: 1px solid #667eea;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-left: auto;
        }

        .content {
            padding: 30px;
        }

        /* Horizontal sweeper: columns that do not fit stay reachable by scrolling */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 14px;
            text-align: right;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            white-space: nowrap;
        }

        th:first-child, td:first-child {
            text-align: left;
        }

        th {
            color: #333;
            font-weight: 700;
            border-bottom: 2px solid #dee2e6;
            cursor: pointer;
            user-select: none;
        }

        th .sort-arrow {
            font-size: 11px;
            color: #667eea;
        }

        tr:hover td {
            background: #f8f9fa;
        }

        .stock-name {
            font-weight: 600;
            color: #2962ff;
        }

        .stock-name a {
            color: inherit;
            text-decoration: none;
        }

        .stock-name a:hover {
            text-decoration: underline;
        }

        .stock-symbol {
            font-size: 12px;
            color: #999;
        }

        .green { color: #26a69a; font-weight: 600; }
        .orange { color: #ff9800; font-weight: 600; }
        .red { color: #ef5350; font-weight: 600; }
        .muted { color: #aaa; }

        .loading, .empty {
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

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .reset-btn {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔎 Stock Screener</h1>
            <p>Growth and risk metrics computed from price history</p>
            <nav>
                <a href="/stocks">📈 Chart</a>
                <a href="/screener">🔎 Screener</a>
            </nav>
        </div>

        <div class="controls">
            <button id="growthPreset" class="preset-btn">🌱 GROWTH filter</button>

            <div class="control-group">
                <label for="minGrowth">Min G %:</label>
                <input type="number" id="minGrowth" placeholder="any" step="1">
            </div>

            <div class="control-group">
                <label for="minRisk">Min R:</label>
                <select id="minRisk">
                    <option value="">any</option>
                    <option value="1">1/6</option>
                    <option value="2">2/6</option>
                    <option value="3">3/6</option>
                    <option value="4">4/6</option>
                    <option value="5">5/6</option>
                    <option value="6">6/6</option>
                </select>
            </div>

            <div class="control-group">
                <label for="trend">H Trend:</label>
                <select id="trend">
                    <option value="">any</option>
                    <option value="UP">UP</option>
                    <option value="DOWN">DOWN</option>
                </select>
            </div>

            <button id="resetFilters" class="reset-btn">Reset filters</button>
        </div>

        <div class="content">
            <div id="error" class="error" style="display: none;"></div>
            <div id="loading" class="loading">Loading screener data...</div>

            <div class="table-wrap">
            <table id="screenerTable" style="display: none;">
                <thead>
                    <tr>
                        <th data-key="symbol">Stock</th>
                        <th data-key="last_close" title="Last close price">Last</th>
                        <th data-key="return_1m" title="Return over the last month">1M</th>
                        <th data-key="return_3m" title="Return over the last 3 months">3M</th>
                        <th data-key="return_6m" title="Return over the last 6 months">6M</th>
                        <th data-key="return_1y" title="Return over the last year">1Y</th>
                        <th data-key="revenue_growth" title="Revenue growth 5Y: annualized revenue growth from financial statements (php artisan stocks:fundamentals)">G</th>
                        <th data-key="growth" title="Annualized price growth rate (CAGR) over the full history">CAGR</th>
                        <th data-key="r_score" title="Reliability: 5Y fundamental checklist when available, otherwise price-based health checks">R</th>
                        <th data-key="off_52w_high" title="Distance from the 52-week high">Δ 52W High</th>
                        <th data-key="volatility" title="Annualized volatility of daily returns">Volatility</th>
                        <th data-key="ema_trend" title="Price vs 50-week EMA">H</th>
                    </tr>
                </thead>
                <tbody id="screenerBody"></tbody>
            </table>
            </div>
            <div id="empty" class="empty" style="display: none;">No stocks match the current filters.</div>
        </div>
    </div>

    <script>
        let allRows = [];
        let sortKey = 'growth';
        let sortDesc = true;

        const GROWTH_PRESET = { minGrowth: 10, minRisk: 4 };

        const errorDiv = document.getElementById('error');
        const loadingDiv = document.getElementById('loading');
        const table = document.getElementById('screenerTable');
        const tbody = document.getElementById('screenerBody');
        const emptyDiv = document.getElementById('empty');
        const minGrowthInput = document.getElementById('minGrowth');
        const minRiskSelect = document.getElementById('minRisk');
        const trendSelect = document.getElementById('trend');
        const growthPresetBtn = document.getElementById('growthPreset');

        function stockSubline(r) {
            const parts = [r.symbol];
            if (r.sector) parts.push(r.sector);
            if (r.industry && r.industry !== r.sector) parts.push(r.industry);
            parts.push(r.last_date);
            return parts.join(' · ');
        }

        function pctClass(v) {
            if (v === null || v === undefined) return 'muted';
            return v >= 0 ? 'green' : 'red';
        }

        function growthClass(v) {
            if (v === null || v === undefined) return 'muted';
            if (v >= 10) return 'green';
            if (v >= 0) return 'orange';
            return 'red';
        }

        function riskClass(score, max) {
            if (!max) return 'muted';
            const ratio = score / max;
            if (ratio >= 0.8) return 'green';
            if (ratio >= 0.5) return 'orange';
            return 'red';
        }

        function fmtPct(v) {
            if (v === null || v === undefined) return '—';
            return (v >= 0 ? '+' : '') + v.toFixed(2) + '%';
        }

        function riskTooltip(row) {
            if (row.r_fundamental) {
                return '5Y fundamental reliability:\n' + Object.entries(row.r_checks)
                    .map(([name, check]) => (check.passed ? '✓ ' : '✗ ') + name + ' — ' + check.details)
                    .join('\n');
            }
            return 'Price-based checks (no fundamentals fetched yet):\n' + Object.entries(row.r_checks)
                .map(([name, passed]) => {
                    if (passed === null) return '– ' + name + ' (not enough history)';
                    return (passed ? '✓ ' : '✗ ') + name;
                })
                .join('\n');
        }

        function currentFilters() {
            return {
                minGrowth: minGrowthInput.value === '' ? null : parseFloat(minGrowthInput.value),
                minRisk: minRiskSelect.value === '' ? null : parseInt(minRiskSelect.value),
                trend: trendSelect.value || null,
            };
        }

        function applyFilters(rows) {
            const f = currentFilters();
            return rows.filter(r => {
                if (f.minGrowth !== null && (r.revenue_growth === null || r.revenue_growth < f.minGrowth)) return false;
                if (f.minRisk !== null && r.r_score < f.minRisk) return false;
                if (f.trend !== null && r.ema_trend !== f.trend) return false;
                return true;
            });
        }

        function sortRows(rows) {
            return [...rows].sort((a, b) => {
                const av = a[sortKey], bv = b[sortKey];
                if (av === null || av === undefined) return 1;
                if (bv === null || bv === undefined) return -1;
                const cmp = typeof av === 'string' ? av.localeCompare(bv) : av - bv;
                return sortDesc ? -cmp : cmp;
            });
        }

        function render() {
            const rows = sortRows(applyFilters(allRows));

            document.querySelectorAll('th .sort-arrow').forEach(el => el.remove());
            const activeTh = document.querySelector(`th[data-key="${sortKey}"]`);
            if (activeTh) {
                activeTh.insertAdjacentHTML('beforeend', ` <span class="sort-arrow">${sortDesc ? '▼' : '▲'}</span>`);
            }

            tbody.innerHTML = rows.map(r => `
                <tr>
                    <td class="stock-name">
                        <a href="/stocks">${r.name || r.symbol}</a><br>
                        <span class="stock-symbol">${stockSubline(r)}</span>
                    </td>
                    <td>${r.last_close.toFixed(2)}</td>
                    <td class="${pctClass(r.return_1m)}">${fmtPct(r.return_1m)}</td>
                    <td class="${pctClass(r.return_3m)}">${fmtPct(r.return_3m)}</td>
                    <td class="${pctClass(r.return_6m)}">${fmtPct(r.return_6m)}</td>
                    <td class="${pctClass(r.return_1y)}">${fmtPct(r.return_1y)}</td>
                    <td class="${growthClass(r.revenue_growth)}">${fmtPct(r.revenue_growth)}</td>
                    <td class="${growthClass(r.growth)}">${fmtPct(r.growth)}</td>
                    <td class="${riskClass(r.r_score, r.r_max)}" title="${riskTooltip(r)}">${r.r_max ? `${r.r_score}/${r.r_max}` : '—'}</td>
                    <td class="${pctClass(r.off_52w_high)}">${fmtPct(r.off_52w_high)}</td>
                    <td>${r.volatility === null ? '—' : r.volatility.toFixed(1) + '%'}</td>
                    <td class="${r.ema_trend === 'UP' ? 'green' : r.ema_trend === 'DOWN' ? 'red' : 'muted'}">${r.ema_trend || '—'}</td>
                </tr>
            `).join('');

            table.style.display = rows.length ? 'table' : 'none';
            emptyDiv.style.display = rows.length ? 'none' : 'block';
        }

        function syncPresetState() {
            const f = currentFilters();
            const active = f.minGrowth === GROWTH_PRESET.minGrowth && f.minRisk === GROWTH_PRESET.minRisk;
            growthPresetBtn.classList.toggle('active', active);
        }

        growthPresetBtn.addEventListener('click', () => {
            if (growthPresetBtn.classList.contains('active')) {
                minGrowthInput.value = '';
                minRiskSelect.value = '';
            } else {
                minGrowthInput.value = GROWTH_PRESET.minGrowth;
                minRiskSelect.value = GROWTH_PRESET.minRisk;
            }
            syncPresetState();
            render();
        });

        document.getElementById('resetFilters').addEventListener('click', () => {
            minGrowthInput.value = '';
            minRiskSelect.value = '';
            trendSelect.value = '';
            syncPresetState();
            render();
        });

        [minGrowthInput, minRiskSelect, trendSelect].forEach(el => {
            el.addEventListener('input', () => { syncPresetState(); render(); });
        });

        document.querySelectorAll('th[data-key]').forEach(th => {
            th.addEventListener('click', () => {
                const key = th.dataset.key;
                if (sortKey === key) {
                    sortDesc = !sortDesc;
                } else {
                    sortKey = key;
                    sortDesc = key !== 'symbol';
                }
                render();
            });
        });

        async function load() {
            try {
                const response = await fetch('/api/screener');
                if (!response.ok) {
                    throw new Error('Failed to load screener data');
                }
                // Normalize R: fundamental reliability when available, else price-based checks
                allRows = (await response.json()).map(r => ({
                    ...r,
                    r_score: r.reliability_max ? r.reliability_score : r.risk_score,
                    r_max: r.reliability_max || r.risk_max,
                    r_checks: r.reliability_max ? r.reliability_checks : r.risk_checks,
                    r_fundamental: !!r.reliability_max,
                }));
                loadingDiv.style.display = 'none';

                if (!allRows.length) {
                    emptyDiv.textContent = 'No stocks in the database. Run "php artisan stocks:fetch" first.';
                    emptyDiv.style.display = 'block';
                    return;
                }

                render();
            } catch (error) {
                loadingDiv.style.display = 'none';
                errorDiv.style.display = 'block';
                errorDiv.textContent = 'Error: ' + error.message;
            }
        }

        load();
    </script>
</body>
</html>
