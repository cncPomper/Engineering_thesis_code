<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Market Visualization</title>
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
            height: 400px;
            margin-bottom: 30px;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }

        .grid-section {
            margin-top: 40px;
        }

        .grid-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .grid-cell {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .grid-cell:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }

        .grid-cell.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .grid-cell-chart {
            position: relative;
            height: 60px;
            margin-bottom: 8px;
        }

        .grid-cell-info {
            font-size: 12px;
            line-height: 1.4;
        }

        .grid-cell-info .date-range {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .grid-cell-info .ohlc {
            font-size: 11px;
            color: #666;
        }

        .grid-cell.active .grid-cell-info .ohlc {
            color: rgba(255, 255, 255, 0.8);
        }

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

            .grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 10px;
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
            <p>Interactive grid visualization with price range filtering</p>
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
                <label for="symbol">Stock (GPW):</label>
                <select id="symbol">
                    <option value="CDR">CDR - CD Projekt Red (Gaming)</option>
                    <option value="PKN">PKN - PKN Orlen (Energy)</option>
                    <option value="MBK">MBK - mBank (Banking)</option>
                    <option value="PLY">PLY - Play Communications (Telecom)</option>
                    <option value="KGH">KGH - KGHM Polska Miedź (Mining)</option>
                    <option value="TPE">TPE - Tauron Polska Energia (Energy)</option>
                </select>
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
                <div class="chart-section">
                    <div class="chart-title">
                        <span id="chartTitle">Daily Price Movement</span>
                        <span id="selectedRange" style="color: #999; font-size: 14px;"></span>
                    </div>
                    <div id="priceChart" class="chart-container"></div>

                    <div class="info-box" id="statsBox" style="display: none;">
                        <strong>Selected Period Stats:</strong>
                        <div id="stats" style="margin-top: 10px;"></div>
                    </div>
                </div>

                <div class="grid-section">
                    <div class="grid-title">Grid Thumbnails - Click to View Details</div>
                    <div class="grid" id="gridContainer"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentData = null;
        let currentTimeframe = '1D';
        let selectedGridCell = null;
        let chart = null;
        let candlestickSeries = null;

        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        const symbolSelect = document.getElementById('symbol');
        const timeframeButtons = document.querySelectorAll('.timeframe-btn');
        const errorDiv = document.getElementById('error');
        const loadingDiv = document.getElementById('loading');
        const mainChartDiv = document.getElementById('mainChart');

        // Event listeners
        startDateInput.addEventListener('change', fetchData);
        endDateInput.addEventListener('change', fetchData);
        symbolSelect.addEventListener('change', fetchData);

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

        async function fetchData() {
            loadingDiv.style.display = 'block';
            mainChartDiv.style.display = 'none';
            errorDiv.style.display = 'none';

            const start = formatDateForAPI(startDateInput.value);
            const end = formatDateForAPI(endDateInput.value);
            const symbol = symbolSelect.value;

            try {
                const response = await fetch(
                    `/api/stocks/range?start=${start}&end=${end}&timeframe=${currentTimeframe}&symbol=${symbol}`
                );

                if (!response.ok) {
                    throw new Error('Failed to fetch data');
                }

                currentData = await response.json();
                loadingDiv.style.display = 'none';
                mainChartDiv.style.display = 'block';

                renderChart(currentData);
                renderGrid(currentData);
            } catch (error) {
                loadingDiv.style.display = 'none';
                errorDiv.style.display = 'block';
                errorDiv.textContent = 'Error: ' + error.message;
            }
        }

        function renderChart(data) {
            const container = document.getElementById('priceChart');
            const chartData = data.data;

            container.innerHTML = '';

            // Create SVG for candlestick chart
            const margin = { top: 20, right: 30, bottom: 30, left: 60 };
            const width = container.clientWidth - margin.left - margin.right;
            const height = 400 - margin.top - margin.bottom;

            // Calculate price range
            const prices = chartData.flatMap(d => [d.open, d.high, d.low, d.close]);
            const minPrice = Math.min(...prices);
            const maxPrice = Math.max(...prices);
            const priceRange = maxPrice - minPrice || 1;

            // Create SVG
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('width', container.clientWidth);
            svg.setAttribute('height', 400);
            svg.setAttribute('style', 'background-color: #f8f9fa; display: block; width: 100%;');

            // Draw Y-axis (prices)
            const yAxisGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            for (let i = 0; i <= 5; i++) {
                const price = minPrice + (priceRange / 5) * i;
                const y = height - (price - minPrice) / priceRange * height + margin.top;

                const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line.setAttribute('x1', margin.left);
                line.setAttribute('y1', y);
                line.setAttribute('x2', container.clientWidth);
                line.setAttribute('y2', y);
                line.setAttribute('stroke', '#e0e0e0');
                line.setAttribute('stroke-width', '1');
                svg.appendChild(line);

                const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                text.setAttribute('x', margin.left - 10);
                text.setAttribute('y', y + 5);
                text.setAttribute('text-anchor', 'end');
                text.setAttribute('font-size', '12');
                text.setAttribute('fill', '#666');
                text.textContent = price.toFixed(2);
                svg.appendChild(text);
            }

            // Calculate candlestick width
            const candleWidth = Math.max(5, width / chartData.length * 0.8);
            const spacing = width / chartData.length;

            // Draw candlesticks
            chartData.forEach((d, idx) => {
                const x = margin.left + idx * spacing + spacing / 2;

                const highY = height - (d.high - minPrice) / priceRange * height + margin.top;
                const lowY = height - (d.low - minPrice) / priceRange * height + margin.top;
                const openY = height - (d.open - minPrice) / priceRange * height + margin.top;
                const closeY = height - (d.close - minPrice) / priceRange * height + margin.top;

                const isUp = d.close >= d.open;
                const bodyTop = Math.min(openY, closeY);
                const bodyHeight = Math.abs(closeY - openY) || 1;

                // Wick (line from low to high)
                const wick = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                wick.setAttribute('x1', x);
                wick.setAttribute('y1', lowY);
                wick.setAttribute('x2', x);
                wick.setAttribute('y2', highY);
                wick.setAttribute('stroke', isUp ? '#26a69a' : '#ef5350');
                wick.setAttribute('stroke-width', '1');
                svg.appendChild(wick);

                // Body (rectangle from open to close)
                const body = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                body.setAttribute('x', x - candleWidth / 2);
                body.setAttribute('y', bodyTop);
                body.setAttribute('width', candleWidth);
                body.setAttribute('height', bodyHeight);
                body.setAttribute('fill', isUp ? '#26a69a' : '#ef5350');
                body.setAttribute('stroke', isUp ? '#1a5d4d' : '#b83a32');
                body.setAttribute('stroke-width', '1');
                body.setAttribute('style', 'cursor: pointer;');
                body.title = `${d.date || d.period}: O:${d.open.toFixed(2)} H:${d.high.toFixed(2)} L:${d.low.toFixed(2)} C:${d.close.toFixed(2)}`;
                svg.appendChild(body);
            });

            // Draw X-axis
            const xAxisLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            xAxisLine.setAttribute('x1', margin.left);
            xAxisLine.setAttribute('y1', height + margin.top);
            xAxisLine.setAttribute('x2', container.clientWidth);
            xAxisLine.setAttribute('y2', height + margin.top);
            xAxisLine.setAttribute('stroke', '#999');
            xAxisLine.setAttribute('stroke-width', '2');
            svg.appendChild(xAxisLine);

            // Draw Y-axis
            const yAxisLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            yAxisLine.setAttribute('x1', margin.left);
            yAxisLine.setAttribute('y1', margin.top);
            yAxisLine.setAttribute('x2', margin.left);
            yAxisLine.setAttribute('y2', height + margin.top);
            yAxisLine.setAttribute('stroke', '#999');
            yAxisLine.setAttribute('stroke-width', '2');
            svg.appendChild(yAxisLine);

            // Add every 5th date label on X-axis
            chartData.forEach((d, idx) => {
                if (idx % Math.ceil(chartData.length / 6) === 0) {
                    const x = margin.left + idx * spacing + spacing / 2;
                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.setAttribute('x', x);
                    text.setAttribute('y', height + margin.top + 20);
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('font-size', '11');
                    text.setAttribute('fill', '#666');
                    text.textContent = d.date ? d.date.substring(0, 5) : d.period;
                    svg.appendChild(text);
                }
            });

            container.appendChild(svg);

            document.getElementById('chartTitle').textContent =
                `${getCompanyName(data.symbol)} (${data.symbol}) - ${getTimeframeLabel(currentTimeframe)}`;
            document.getElementById('selectedRange').textContent =
                ` (${data.start} to ${data.end})`;
        }

        function renderGrid(data) {
            const gridContainer = document.getElementById('gridContainer');
            gridContainer.innerHTML = '';

            data.grid.forEach((cell, idx) => {
                const div = document.createElement('div');
                div.className = 'grid-cell';
                div.onclick = () => selectGridCell(cell, idx, data.grid);

                const miniChart = createMiniChart(cell);
                const info = `
                    <div class="date-range">${cell.start_date}<br>${cell.end_date}</div>
                    <div class="ohlc">
                        O: ${cell.open.toFixed(2)}<br>
                        H: ${cell.high.toFixed(2)}<br>
                        L: ${cell.low.toFixed(2)}<br>
                        C: ${cell.close.toFixed(2)}
                    </div>
                `;

                div.innerHTML = `
                    <div class="grid-cell-chart">${miniChart}</div>
                    <div class="grid-cell-info">${info}</div>
                `;

                gridContainer.appendChild(div);
            });
        }

        function createMiniChart(cell) {
            if (!cell.data || !Array.isArray(cell.data) || cell.data.length === 0) {
                return '<svg></svg>';
            }

            const closeValues = cell.data
                .map(d => typeof d === 'object' ? d.close : parseFloat(d))
                .filter(v => !isNaN(v));

            if (closeValues.length === 0) {
                return '<svg></svg>';
            }

            const min = Math.min(...closeValues);
            const max = Math.max(...closeValues);
            const range = max - min || 1;
            const width = 140;
            const height = 60;
            const padding = 5;

            const points = closeValues.map((value, i) => {
                const x = (i / (closeValues.length - 1 || 1)) * (width - padding * 2) + padding;
                const y = height - padding - ((value - min) / range) * (height - padding * 2);
                return `${x},${y}`;
            }).join(' ');

            return `<svg width="100%" height="100%" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none">
                <polyline points="${points}" fill="none" stroke="#667eea" stroke-width="2" vector-effect="non-scaling-stroke"/>
            </svg>`;
        }

        function selectGridCell(cell, idx, grid) {
            document.querySelectorAll('.grid-cell').forEach((el, i) => {
                el.classList.toggle('active', i === idx);
            });

            const stats = `
                <strong>Period: ${cell.start_date} to ${cell.end_date}</strong><br>
                Open: ${cell.open.toFixed(4)} | High: ${cell.high.toFixed(4)} | Low: ${cell.low.toFixed(4)} | Close: ${cell.close.toFixed(4)}<br>
                Volume: ${(cell.volume / 1000).toFixed(0)}K | Days: ${cell.count}
            `;
            document.getElementById('stats').innerHTML = stats;
            document.getElementById('statsBox').style.display = 'block';

            if (cell.data && cell.data.length > 0) {
                renderDetailedChart(cell);
            }
        }

        function renderDetailedChart(cell) {
            if (!cell.data || cell.data.length === 0) return;

            const container = document.getElementById('priceChart');
            container.innerHTML = '';

            // Create SVG for candlestick chart
            const margin = { top: 20, right: 30, bottom: 30, left: 60 };
            const width = container.clientWidth - margin.left - margin.right;
            const height = 400 - margin.top - margin.bottom;

            // Calculate price range
            const prices = cell.data.map(d => [d.open, d.high, d.low, d.close]).flat();
            const minPrice = Math.min(...prices);
            const maxPrice = Math.max(...prices);
            const priceRange = maxPrice - minPrice || 1;

            // Create SVG
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('width', container.clientWidth);
            svg.setAttribute('height', 400);
            svg.setAttribute('style', 'background-color: #f8f9fa; display: block; width: 100%;');

            // Draw Y-axis (prices)
            for (let i = 0; i <= 5; i++) {
                const price = minPrice + (priceRange / 5) * i;
                const y = height - (price - minPrice) / priceRange * height + margin.top;

                const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line.setAttribute('x1', margin.left);
                line.setAttribute('y1', y);
                line.setAttribute('x2', container.clientWidth);
                line.setAttribute('y2', y);
                line.setAttribute('stroke', '#e0e0e0');
                line.setAttribute('stroke-width', '1');
                svg.appendChild(line);

                const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                text.setAttribute('x', margin.left - 10);
                text.setAttribute('y', y + 5);
                text.setAttribute('text-anchor', 'end');
                text.setAttribute('font-size', '12');
                text.setAttribute('fill', '#666');
                text.textContent = price.toFixed(2);
                svg.appendChild(text);
            }

            // Calculate candlestick width
            const candleWidth = Math.max(8, width / cell.data.length * 0.8);
            const spacing = width / cell.data.length;

            // Draw candlesticks
            cell.data.forEach((d, idx) => {
                const x = margin.left + idx * spacing + spacing / 2;

                const highY = height - (d.high - minPrice) / priceRange * height + margin.top;
                const lowY = height - (d.low - minPrice) / priceRange * height + margin.top;
                const openY = height - (d.open - minPrice) / priceRange * height + margin.top;
                const closeY = height - (d.close - minPrice) / priceRange * height + margin.top;

                const isUp = d.close >= d.open;
                const bodyTop = Math.min(openY, closeY);
                const bodyHeight = Math.abs(closeY - openY) || 1;

                // Wick (line from low to high)
                const wick = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                wick.setAttribute('x1', x);
                wick.setAttribute('y1', lowY);
                wick.setAttribute('x2', x);
                wick.setAttribute('y2', highY);
                wick.setAttribute('stroke', isUp ? '#26a69a' : '#ef5350');
                wick.setAttribute('stroke-width', '1');
                svg.appendChild(wick);

                // Body (rectangle from open to close)
                const body = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                body.setAttribute('x', x - candleWidth / 2);
                body.setAttribute('y', bodyTop);
                body.setAttribute('width', candleWidth);
                body.setAttribute('height', bodyHeight);
                body.setAttribute('fill', isUp ? '#26a69a' : '#ef5350');
                body.setAttribute('stroke', isUp ? '#1a5d4d' : '#b83a32');
                body.setAttribute('stroke-width', '1');
                body.setAttribute('style', 'cursor: pointer;');
                body.title = `${d.date}: O:${d.open.toFixed(2)} H:${d.high.toFixed(2)} L:${d.low.toFixed(2)} C:${d.close.toFixed(2)}`;
                svg.appendChild(body);
            });

            // Draw X-axis
            const xAxisLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            xAxisLine.setAttribute('x1', margin.left);
            xAxisLine.setAttribute('y1', height + margin.top);
            xAxisLine.setAttribute('x2', container.clientWidth);
            xAxisLine.setAttribute('y2', height + margin.top);
            xAxisLine.setAttribute('stroke', '#999');
            xAxisLine.setAttribute('stroke-width', '2');
            svg.appendChild(xAxisLine);

            // Draw Y-axis
            const yAxisLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            yAxisLine.setAttribute('x1', margin.left);
            yAxisLine.setAttribute('y1', margin.top);
            yAxisLine.setAttribute('x2', margin.left);
            yAxisLine.setAttribute('y2', height + margin.top);
            yAxisLine.setAttribute('stroke', '#999');
            yAxisLine.setAttribute('stroke-width', '2');
            svg.appendChild(yAxisLine);

            // Add date labels on X-axis
            cell.data.forEach((d, idx) => {
                if (idx % Math.ceil(cell.data.length / 5) === 0) {
                    const x = margin.left + idx * spacing + spacing / 2;
                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.setAttribute('x', x);
                    text.setAttribute('y', height + margin.top + 20);
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('font-size', '11');
                    text.setAttribute('fill', '#666');
                    text.textContent = d.date.substring(0, 5);
                    svg.appendChild(text);
                }
            });

            container.appendChild(svg);
        }

        function getTimeframeLabel(tf) {
            const labels = { '1D': 'Daily', '1W': 'Weekly', '1M': 'Monthly' };
            return labels[tf] || tf;
        }

        function getCompanyName(symbol) {
            const names = {
                'CDR': 'CD Projekt Red',
                'PKN': 'PKN Orlen',
                'MBK': 'mBank',
                'PLY': 'Play Communications',
                'KGH': 'KGHM Polska Miedź',
                'TPE': 'Tauron Polska Energia'
            };
            return names[symbol] || symbol;
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

        // Initialize dates on page load
        setDefaultDates();

        // Initial load
        fetchData();
    </script>
</body>
</html>
