# Stock API Documentation

## ✅ Implementation Complete

The Stock API with grid-based visualization is fully functional and deployed locally.

---

## Quick Start

```bash
# 1. Activate virtual environment
.\.venv\Scripts\Activate.ps1

# 2. Start server (if not running)
php artisan serve --port=8000

# 3. Test API
curl "http://localhost:8000/api/stocks/range?start=01.04.2026&end=05.07.2026&timeframe=1D&symbol=MOC"
```

---

## API Endpoint

### `GET /api/stocks/range`

Returns stock data with grid visualization for interactive charts.

**Query Parameters:**

| Parameter | Required | Format | Values | Default |
|-----------|----------|--------|--------|---------|
| `start` | ✓ | `d.m.Y` | `01.01.2026` | - |
| `end` | ✓ | `d.m.Y` | `05.07.2026` | - |
| `timeframe` | ✗ | enum | `1D`, `1W`, `1M` | `1D` |
| `symbol` | ✗ | enum | `MOC`, `AMB` | `MOC` |

**Example Requests:**

```bash
# Daily data for MOC
GET /api/stocks/range?start=01.04.2026&end=05.07.2026&timeframe=1D&symbol=MOC

# Weekly aggregation for AMB
GET /api/stocks/range?start=01.04.2026&end=05.07.2026&timeframe=1W&symbol=AMB

# Monthly aggregation
GET /api/stocks/range?start=01.04.2026&end=05.07.2026&timeframe=1M&symbol=MOC
```

---

## Response Structure

```json
{
  "timeframe": "1D",
  "symbol": "MOC",
  "start": "01.04.2026",
  "end": "05.07.2026",
  "data": [
    {
      "date": "07.04.2026",
      "open": 5.30,
      "high": 5.30,
      "low": 5.20,
      "close": 5.27,
      "volume": 39562
    }
  ],
  "grid": [
    {
      "start_date": "07.04.2026",
      "end_date": "20.04.2026",
      "open": 5.30,
      "high": 5.58,
      "low": 5.08,
      "close": 5.25,
      "volume": 721098,
      "count": 10,
      "data": [
        // Full 10 daily candles for this grid cell
      ]
    }
  ]
}
```

### Response Fields

**Top-level:**
- `timeframe`: Requested timeframe (1D, 1W, or 1M)
- `symbol`: Stock symbol (MOC or AMB)
- `start`: Start date of range
- `end`: End date of range
- `data`: Full detail data (daily, weekly, or monthly)
- `grid`: Chunked data for grid visualization (10 items per chunk)

**Data Array Items:**
- `date` / `period`: Date or period key
- `open`: Opening price
- `high`: Highest price in period
- `low`: Lowest price in period
- `close`: Closing price
- `volume`: Trading volume

**Grid Items:**
- `start_date` / `end_date`: Date range for chunk
- `open`, `high`, `low`, `close`: Aggregated OHLC
- `volume`: Total volume for chunk
- `count`: Number of candles in chunk
- `data`: Array of full candles for this chunk

---

## Timeframe Aggregation

### 1D (Daily) - Base Data
- Raw daily OHLC data from database
- 62 records for each stock (Apr 5 - Jul 5, 2026)
- Used for detailed analysis and grid thumbnails

### 1W (Weekly) - Calculated
- Aggregated from daily data
- Open: First day's open
- High: Maximum high in week
- Low: Minimum low in week
- Close: Last day's close
- Volume: Sum of weekly volume

**Example:** Week 2026-15 = Apr 7-20, 2026

### 1M (Monthly) - Calculated
- Aggregated from daily data
- Open: First day's open
- High: Maximum high in month
- Low: Minimum low in month
- Close: Last day's close
- Volume: Sum of monthly volume

**Example:** Month 2026-04 = April 2026

---

## Grid Visualization

The `grid` array enables "click to zoom" functionality:

1. **Grid Display (Thumbnails)**
   - 10 daily candles per grid cell
   - Shows aggregated OHLC for quick overview
   - Last grid cell may have fewer than 10 items

2. **Click Handler**
   - Click grid cell → fetch full candle data from `data` array
   - Render detailed chart with 10 daily candles
   - Display OHLC and volume for each day

3. **Grid Structure**
   ```
   [Grid Cell 1: 10 candles] [Grid Cell 2: 10 candles] ...
   Apr 7-20                    Apr 21-May 5
   ↓ Click                     ↓ Click
   Shows detailed chart        Shows detailed chart
   with all 10 daily candles   with all 10 daily candles
   ```

---

## Stock Data

### MOC - mBank S.A.
- Symbol: MOC (MOC.WA on Warsaw Exchange)
- Data: 62 daily records (Apr 7 - Jul 3, 2026)
- Price range: ~5.08 - 5.99 PLN

### AMB - AmRest Holdings SE
- Symbol: AMB (AMB.WA on Warsaw Exchange)
- Data: 62 daily records (Apr 7 - Jul 3, 2026)
- Price range: ~17.52 - 20.20 PLN

---

## Data Source

- **Provider:** yfinance (Python)
- **Exchange:** GPW (Giełda Papierów Wartościowych - Warsaw Stock Exchange)
- **Symbols:** MOC.WA, AMB.WA
- **Update Frequency:** On-demand via `php artisan stocks:fetch`

**To update data:**
```bash
php artisan stocks:fetch --symbols=MOC.WA,AMB.WA --start=2026-01-01 --end=2026-07-05
```

---

## Database

**Connection:** SQLite (database/database.sqlite)

**Table:** `stocks`
```sql
CREATE TABLE stocks (
  id BIGINT PRIMARY KEY,
  symbol VARCHAR(50),
  date DATE,
  open FLOAT,
  high FLOAT,
  low FLOAT,
  close FLOAT,
  volume BIGINT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  UNIQUE(symbol, date),
  INDEX(symbol, date)
);
```

---

## Frontend Integration

### React/Vue Example

```javascript
// Fetch grid data
const response = await fetch(
  '/api/stocks/range?start=01.04.2026&end=05.07.2026&timeframe=1D'
);
const { grid, data } = await response.json();

// Render grid thumbnails
grid.forEach(cell => {
  renderThumbnail(cell); // 10 candles per cell
});

// On grid click
function onGridCellClick(cellIndex) {
  const cell = grid[cellIndex];
  renderDetailedChart(cell.data); // Full 10 candles
}
```

---

## Testing

**Daily data:**
```bash
curl "http://localhost:8000/api/stocks/range?start=01.04.2026&end=05.07.2026&timeframe=1D&symbol=MOC"
```

**Weekly aggregation:**
```bash
curl "http://localhost:8000/api/stocks/range?start=01.04.2026&end=05.07.2026&timeframe=1W&symbol=MOC"
```

**Monthly aggregation:**
```bash
curl "http://localhost:8000/api/stocks/range?start=01.04.2026&end=05.07.2026&timeframe=1M&symbol=MOC"
```

**Different stock:**
```bash
curl "http://localhost:8000/api/stocks/range?start=01.04.2026&end=05.07.2026&symbol=AMB"
```

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Frontend (React/Vue)                    │
│  - Display grid thumbnails (10 candles per cell)           │
│  - Handle click events for zoom                             │
│  - Render detailed charts                                   │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTP GET
                       ↓
┌─────────────────────────────────────────────────────────────┐
│              Laravel API Controller                         │
│  - Validate request parameters                             │
│  - Query database for date range                           │
│  - Calculate aggregations (1W, 1M)                         │
│  - Create grid chunks (10 per cell)                        │
│  - Return JSON response                                     │
└──────────────────────┬──────────────────────────────────────┘
                       │ SQL Query
                       ↓
┌─────────────────────────────────────────────────────────────┐
│         SQLite Database                                    │
│  - stocks table (62 daily records per symbol)              │
│  - Indexed by (symbol, date)                               │
└──────────────────────┬──────────────────────────────────────┘
                       │ On-demand
                       ↓
┌─────────────────────────────────────────────────────────────┐
│       Python yfinance Integration                          │
│  - fetch_stock_data.py script                              │
│  - Console command: stocks:fetch                           │
│  - Updates database with latest data                       │
└─────────────────────────────────────────────────────────────┘
```

---

## Key Features Implemented

✅ **Storage:** 1D data in SQLite database
✅ **Aggregation:** 1W and 1M calculated on-the-fly
✅ **Grid Visualization:** 10 candles per grid cell
✅ **Click to Zoom:** Full data available per grid chunk
✅ **Data Source:** yfinance integration for GPW stocks
✅ **Timeframe Support:** Daily, Weekly, Monthly
✅ **Multiple Stocks:** MOC and AMB supported
✅ **API Validation:** Date format and symbol validation
✅ **Performance:** Indexed database queries
✅ **Error Handling:** Proper HTTP error responses

---

## Troubleshooting

**"No data found"**
→ Check date range is within data period (Apr 7 - Jul 3, 2026)

**Database errors**
→ Verify SQLite file exists: `database/database.sqlite`

**yfinance errors when fetching**
→ Verify `.venv` is activated and yfinance is installed
→ Run: `.\.venv\Scripts\Activate.ps1 && uv pip list | grep yfinance`

**Server not running**
→ Start with: `php artisan serve --port=8000`

---

## Next Steps

- [ ] Build React/Vue frontend with grid visualization
- [ ] Add candlestick chart library (Chart.js, TradingView Lightweight Charts)
- [ ] Implement click handlers for grid zoom
- [ ] Add date range picker
- [ ] Add stock symbol selector
- [ ] Add technical indicators (MA, RSI, MACD)
- [ ] Deploy to production server
