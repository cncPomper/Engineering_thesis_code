# Stock Market Visualization Guide

## 🎯 Overview

The visualization interface provides an interactive grid-based chart system for analyzing stock price movements across different timeframes (daily, weekly, monthly).

**Access:** `http://localhost:8000/stocks`

---

## 📊 Features

### 1. **Timeframe Switching**
Three buttons at the top right:
- **Daily (1D)** - Individual daily candles
- **Weekly (1W)** - Aggregated 5-day periods
- **Monthly (1M)** - Full month aggregations

### 2. **Data Selection**
- **Start Date** - Beginning of analysis period
- **End Date** - End of analysis period
- **Stock Symbol** - Choose MOC or AMB

### 3. **Main Chart**
- **Line chart** showing High/Close/Low prices
- **X-axis:** Dates or periods
- **Y-axis:** Stock price
- **Interactive:** Hover to see values
- **Legend:** Toggle High/Close/Low lines

### 4. **Grid Thumbnails**
- **Layout:** Responsive grid of 10-day chunks
- **Each cell shows:**
  - Mini line chart (sparkline)
  - Date range
  - OHLC summary
  - Hover effect highlights cell

### 5. **Click-to-Zoom**
- Click any grid cell
- Main chart updates with full detail of that cell's 10 days
- Statistics box shows:
  - Period dates
  - OHLC values
  - Total volume
  - Number of days

---

## 🖱️ Usage Workflow

### Basic Flow
```
1. Select date range
2. Choose stock (MOC or AMB)
3. Select timeframe (1D, 1W, or 1M)
   ↓
4. Main chart displays with full data
5. Grid shows thumbnail chunks
   ↓
6. Click grid cell to zoom
7. Main chart shows 10-day detail
8. Stats panel updates
```

### Example Scenarios

**Scenario 1: Analyze April Performance (Daily)**
1. Set Start: `2026-04-01`, End: `2026-04-30`
2. Click **Daily (1D)** button
3. Main chart shows 30 daily candles
4. Click any grid cell to see 10-day detail
5. Observe price trends within the month

**Scenario 2: Compare Weekly Aggregations**
1. Set Start: `2026-04-01`, End: `2026-07-05`
2. Click **Weekly (1W)** button
3. Main chart shows 13 five-day aggregations
4. Easy comparison of week-over-week performance

**Scenario 3: Monthly Overview**
1. Keep date range: `2026-04-01` to `2026-07-05`
2. Click **Monthly (1M)** button
3. Main chart shows 4 monthly candles
4. High-level trend analysis

---

## 📈 Chart Features

### Main Chart (Line Chart)
- **Three lines:**
  - 🟢 **Green** - High prices
  - 🔵 **Blue** - Closing prices (thickest line, primary focus)
  - 🔴 **Red** - Low prices

- **Interactive Features:**
  - Hover to see exact values
  - Click legend items to toggle lines
  - Responsive to window resize

### Grid Cells
- **Background Color:** Light gray (default), blue when active
- **Mini Chart:** Sparkline showing price movement in cell
- **Information:**
  ```
  Start Date → End Date
  O: Open    L: Low
  H: High    C: Close
  ```
- **Hover Effect:** Shadow and lift animation
- **Click:** Activates cell and updates main chart

### Statistics Box
- **Only appears when:** Grid cell is selected
- **Shows:**
  - Selected date range
  - OHLC values (4 decimal places)
  - Total trading volume (in thousands)
  - Number of trading days

---

## 🎨 Visual Design

### Color Scheme
- **Header:** Purple gradient (#667eea → #764ba2)
- **Active Elements:** Purple (#667eea)
- **Success:** Green (#28a745)
- **Neutral:** Gray (#e9ecef)
- **Background:** Light gradient

### Responsive Design
- **Desktop:** Full width optimization
- **Tablet:** Adjusted grid layout
- **Mobile:** Single-column layout with buttons stacked
- **Touch-friendly:** Larger tap targets

---

## 📱 Data Structure (Technical)

### API Response Structure
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
      "data": [ /* 10 daily candles */ ]
    }
  ]
}
```

### Grid Cell Structure
Each grid cell contains:
- `start_date` / `end_date` - Period boundaries
- `open`, `high`, `low`, `close` - Aggregated OHLC
- `volume` - Total trading volume
- `count` - Number of candles in cell
- `data` - Array of full daily candles (for detail view)

---

## 🔄 Timeframe Aggregation

### 1D (Daily)
- **Data:** Raw daily OHLC
- **Grid Chunks:** 10 consecutive days per cell
- **Use Case:** Detailed price analysis, technical indicators

### 1W (Weekly / 5-Day)
- **Data:** Aggregated every 5 consecutive trading days
- - **Open:** First day's opening
  - **High:** Maximum high in 5 days
  - **Low:** Minimum low in 5 days
  - **Close:** Last day's closing
  - **Volume:** Sum of 5 days
- **Use Case:** Mid-term trend analysis

### 1M (Monthly)
- **Data:** Full calendar month aggregation
- **Open:** First trading day of month
- **High:** Highest price in entire month
- **Low:** Lowest price in entire month
- **Close:** Last trading day of month
- **Volume:** Total monthly volume
- **Use Case:** Long-term trend, portfolio overview

---

## 🐛 Troubleshooting

### Chart Not Loading
- Check if `/api/stocks/range` endpoint responds
- Verify date range is within data period (Apr 7 - Jul 3, 2026)
- Open browser console (F12) for JavaScript errors

### No Grid Cells Showing
- Ensure date range is not empty
- Try shorter range (single week)
- Check that selected stock has data in period

### Dates Not Formatting Correctly
- Verify date inputs are in `YYYY-MM-DD` format
- Browser converts to this format automatically
- API expects `d.m.Y` format (handled by JavaScript)

### Empty Statistics Box
- Select a grid cell by clicking it
- Statistics only show when cell is active

---

## 🎓 Learning Path

### Beginner
1. Load default MOC data (Apr 1 - Jul 5)
2. Look at **Daily (1D)** view
3. Identify highest and lowest points
4. Click different grid cells to explore

### Intermediate
1. Switch to **Weekly (1W)** aggregation
2. Compare 5-day performance across weeks
3. Notice trends that don't appear in daily view

### Advanced
1. Switch to **Monthly (1M)** aggregation
2. Analyze long-term trends
3. Compare different stocks (MOC vs AMB)
4. Combine with technical analysis skills

---

## 📊 Stock Data

### MOC - mBank S.A.
- Polish banking company
- Price range: ~5.08 - 5.99 PLN
- Trading: Warsaw Stock Exchange (GPW)

### AMB - AmRest Holdings SE
- Restaurant group company
- Price range: ~17.52 - 20.20 PLN
- Trading: Warsaw Stock Exchange (GPW)

---

## 🔗 Related Documentation

- [API_DOCUMENTATION.md](./API_DOCUMENTATION.md) - REST API reference
- [STOCK_SETUP.md](./STOCK_SETUP.md) - Installation and setup
- Source files:
  - [resources/views/stocks/index.blade.php](./resources/views/stocks/index.blade.php)
  - [app/Http/Controllers/StockController.php](./app/Http/Controllers/StockController.php)

---

## 💡 Tips & Tricks

1. **Quick Month View:** Set both dates to same month to focus on single period
2. **Compare Stocks:** Switch between MOC/AMB with same timeframe to see differences
3. **High Resolution:** Zoom browser to 125% for better chart visibility
4. **Mobile:** Works on phones/tablets with responsive grid layout
5. **Performance:** Grid loads instantly; chart rendering takes <1s

---

## 🚀 Future Enhancements

- [ ] Candlestick charts (instead of line charts)
- [ ] Moving averages overlay
- [ ] Volume bars below price chart
- [ ] Technical indicators (RSI, MACD, Bollinger Bands)
- [ ] Export to CSV/PNG
- [ ] Multiple stocks comparison
- [ ] Annotations and markers
- [ ] Price alerts

---

## 📞 Support

For issues or questions:
1. Check console for errors: `F12` → Console tab
2. Verify API is running: Visit `/api/stocks/range?start=01.04.2026&end=05.07.2026`
3. Check database: `php artisan tinker` → `Stock::count()`
