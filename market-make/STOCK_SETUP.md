# Stock API Setup Guide

## Overview
This API provides stock data with grid-based visualization for price range analysis. Data is stored daily (1D) with automatic aggregation to weekly (1W) and monthly (1M).

## Requirements
- PHP 8.0+
- MySQL 5.7+ (or configure SQLite in `.env`)
- Python 3.7+
- yfinance Python package

## Installation Steps

### 1. Install Python Dependencies with uv

First, ensure you have `uv` installed:
```bash
pip install uv
```

Then install project dependencies:
```bash
# Option A: Using pyproject.toml (recommended)
uv pip install -r requirements.txt

# Option B: Using uv with project setup
uv sync
```

### 2. Configure Database

#### Option A: MySQL (Recommended)
Ensure MySQL is running and update `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=your_password
```

Then start MySQL and run:
```bash
php artisan migrate
```

#### Option B: SQLite (Easier for Development)
Update `.env`:
```env
DB_CONNECTION=sqlite
DB_DATABASE=database.sqlite
```

Create the database file:
```bash
touch database/database.sqlite
php artisan migrate
```

### 3. Fetch Stock Data
Fetch historical data for MOC and AMB stocks from GPW (Polish stock exchange):

```bash
# Fetch last 3 months (default)
php artisan stocks:fetch

# Fetch custom date range
php artisan stocks:fetch --start=2026-01-01 --end=2026-07-05

# Fetch specific symbol
php artisan stocks:fetch --symbols=MOC
```

### 4. Start the Server
```bash
php artisan serve
```

## API Endpoint

### GET /api/stocks/range

**Parameters:**
- `start` (required): Start date in format `d.m.Y` (e.g., `01.01.2026`)
- `end` (required): End date in format `d.m.Y` (e.g., `05.07.2026`)
- `timeframe` (optional): `1D`, `1W`, or `1M` (default: `1D`)
- `symbol` (optional): Stock symbol - `MOC` or `AMB` (default: `MOC`)

**Example Requests:**

Daily data for MOC:
```
GET /api/stocks/range?start=01.01.2026&end=05.07.2026&timeframe=1D&symbol=MOC
```

Weekly aggregation:
```
GET /api/stocks/range?start=01.01.2026&end=05.07.2026&timeframe=1W&symbol=AMB
```

Monthly aggregation:
```
GET /api/stocks/range?start=01.01.2026&end=05.07.2026&timeframe=1M&symbol=MOC
```

## Response Format

```json
{
  "timeframe": "1D",
  "symbol": "MOC",
  "start": "01.01.2026",
  "end": "05.07.2026",
  "data": [
    {
      "date": "01.01.2026",
      "open": 42.50,
      "high": 43.20,
      "low": 42.10,
      "close": 43.00,
      "volume": 1000000
    }
  ],
  "grid": [
    {
      "start_date": "01.01.2026",
      "end_date": "15.01.2026",
      "open": 42.50,
      "high": 43.50,
      "low": 42.10,
      "close": 42.80,
      "volume": 10000000,
      "count": 10,
      "data": [
        // 10 daily records
      ]
    }
  ]
}
```

## Frontend Integration

The `grid` array contains chunked data for grid visualization:
- Each grid cell represents 10 daily candles
- Each cell contains aggregated OHLC data and full candle details
- Click on a grid cell to display full data in `data.data` array

## Architecture

**Database Schema:**
- `stocks` table stores daily (1D) data
- Fields: `symbol`, `date`, `open`, `high`, `low`, `close`, `volume`
- Unique index on `(symbol, date)` prevents duplicates

**Data Flow:**
1. Python script (`scripts/fetch_stock_data.py`) fetches from yfinance
2. Console command (`FetchStockData`) processes and stores data
3. Controller aggregates data on-demand for 1W and 1M timeframes
4. API returns grid chunks and full data for visualization

## Troubleshooting

**"No data found"** response:
- Check that yfinance has data for the date range
- Ensure stocks are fetched: `php artisan stocks:fetch`
- MOC and AMB use `.WA` suffix on yfinance (Warsaw exchange)

**Python script errors:**
- Verify yfinance is installed: `uv pip list | grep yfinance`
- Or reinstall: `uv pip install yfinance`
- Check Python path is correct in the command

**Database connection error:**
- Verify MySQL is running
- Check `.env` database credentials
- Or switch to SQLite following the steps above
