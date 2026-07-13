# Market-Make — Stock Visualization & GROWTH Screener

A Laravel application for downloading, storing and analyzing stock market data from
any exchange in the world (via [yfinance](https://pypi.org/project/yfinance/)),
featuring a TradingView-style candlestick chart and a stock screener built around
the GROWTH Investing methodology.

## Features

- **Data pipeline** — incremental daily OHLCV downloads for any yfinance ticker
  (`NVDA`, `CDR.WA`, `BMW.DE`, `3661.TW`, ...), plus company metadata and 5-year
  fundamentals.
- **Chart page (`/stocks`)** — interactive candlestick chart (TradingView
  [Lightweight Charts](https://github.com/tradingview/lightweight-charts)) with
  volume, Donchian Channels (upper 20 / lower 10), Daily/Weekly/Monthly
  timeframes, crosshair OHLC legend, and a G·R·O·W·T·H Quick Analysis strip.
- **Screener page (`/screener`)** — sortable, filterable table of every stock in
  the database with traffic-light coloring and a one-click GROWTH preset filter.

## Requirements

- PHP 8.0+ with Composer
- SQLite (default) or MySQL — configured in `.env`; a `docker-compose.yml` is
  provided for MySQL (see [Database](#database-mysql-via-docker))
- Python 3.8+ in a virtualenv at `.venv/` with `yfinance` (numpy comes with it)

## Setup

```bash
composer install
cp .env.example .env          # then configure DB_* if not using SQLite
php artisan key:generate
docker compose up -d          # MySQL only — see "Database" below
php artisan migrate

# Python side (used by the artisan fetch commands)
python -m venv .venv
.venv/Scripts/pip install yfinance        # Windows
# .venv/bin/pip install yfinance          # Linux/macOS

php artisan serve             # then open http://127.0.0.1:8000/stocks
```

## Database (MySQL via Docker)

The MySQL database runs in Docker, defined in `docker-compose.yml`. It reads
`DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` from `.env`, binds to
`127.0.0.1:13306` and persists data in the `market-make-db-data` volume.

```bash
docker compose up -d          # start (auto-restarts after reboot too)
docker compose stop           # stop
docker compose logs -f db     # tail MySQL logs
docker compose down           # remove the container; data volume is kept
```

Do **not** use `docker compose down -v` — the `-v` flag deletes the data
volume and with it all downloaded stock history.

## Fetching data

### Prices — `stocks:fetch`

```bash
# Incremental update for the default GPW symbols
php artisan stocks:fetch

# Any tickers, any exchange (used verbatim as the yfinance ticker)
php artisan stocks:fetch --symbols=NVDA,CDR.WA,BMW.DE

# Backfill history (both dates inclusive)
php artisan stocks:fetch --symbols=NVDA --start=2020-01-01

# Overwrite rows that already exist (e.g. refresh today's provisional candle)
php artisan stocks:fetch --symbols=NVDA --start=2026-07-10 --force
```

Behavior worth knowing:

- **Incremental by default** — without `--start`, each symbol resumes from the
  day after its latest stored record (falling back to 3 months for new symbols).
  Already up-to-date symbols are skipped entirely.
- **No silent overwrites** — rows already in the database are skipped and
  reported (`✓ NVDA: 12 new, 1628 already in database`); use `--force` to
  refresh them.
- **Company metadata** (name, sector, industry) is fetched alongside prices and
  stored in the `companies` table; it powers the dropdown, chart legend and
  screener labels.

### Fundamentals — `stocks:fundamentals`

```bash
php artisan stocks:fundamentals                  # every symbol in the DB
php artisan stocks:fundamentals --symbols=NVDA
```

Pulls up to 5 fiscal years of income statement / cash flow / balance sheet from
yfinance and stores:

- **G — Revenue growth 5Y**: annualized revenue growth,
- **EPS growth 5Y**: annualized diluted EPS growth,
- **R — Reliability score (x/6)**: the GROWTH Investing checklist implemented in
  [`scripts/growth_reliability.py`](scripts/growth_reliability.py):
  1. Revenue grows every year
  2. No net losses and no income drop > 15% YoY
  3. Free cash flow positive every year
  4. Operating margin sustained (or stdev < 2 pp)
  5. Debt/EBITDA below 3.0x every year
  6. Revenue linearity R² ≥ 0.85

Re-run after earnings seasons to keep the scores current. yfinance usually
exposes only 3–4 annual periods, so the checklist accepts a 3–5 year window.

## Pages

### `/stocks` — chart

- Symbol dropdown mirrors exactly what is in the database.
- Donchian Channels: upper band = highest high of 20 bars (blue), lower band =
  lowest low of 10 bars (red); extra off-screen history is fetched automatically
  so the bands start at the left edge of the visible range.
- Quick Analysis strip (hover any letter for its definition):

  | Letter | Meaning | Source |
  |---|---|---|
  | G | Revenue growth 5Y (annualized) | fundamentals |
  | R | Reliability checklist x/6 | fundamentals, falls back to price-based checks |
  | O | Outlook: 6-month linear trend projected 12 months ahead | prices |
  | W | Margin of safety: distance from 52-week high | prices |
  | T | Trend strength: price vs 200-day EMA | prices |
  | H | 50-week EMA trend (UP/DOWN) | prices |

### `/screener` — screener

- One row per stock with 1M/3M/6M/1Y returns, G (revenue growth), CAGR (price),
  R, distance from 52W high, volatility and H trend.
- Click column headers to sort; the table scrolls horizontally when columns
  overflow.
- **🌱 GROWTH filter** preset: G ≥ 10% and R ≥ 4; or filter manually by min G,
  min R and H trend.
- Hover an R value to see exactly which checklist criteria passed and why.

## API

| Endpoint | Description |
|---|---|
| `GET /api/stocks/symbols` | Symbols in the DB with date range, record count and company info |
| `GET /api/stocks/range?start=01.01.2026&end=10.07.2026&timeframe=1D&symbol=NVDA` | OHLCV series; `timeframe` = `1D`, `1W` (Mon–Fri aggregate) or `1M`; dates in `d.m.Y` |
| `GET /api/screener` | All computed metrics + fundamentals for every symbol |

## Project layout

```
app/Console/Commands/FetchStockData.php     stocks:fetch (prices + company info)
app/Console/Commands/FetchFundamentals.php  stocks:fundamentals (G / R scores)
app/Http/Controllers/StockController.php    /api/stocks/*
app/Http/Controllers/ScreenerController.php /api/screener (price metrics)
scripts/fetch_stock_data.py                 yfinance OHLCV + company metadata
scripts/fetch_fundamentals.py               yfinance financial statements
scripts/growth_reliability.py               GROWTH "R" checklist (standalone, has a demo: run it directly)
resources/views/stocks/index.blade.php      chart page
resources/views/screener.blade.php          screener page
```

## Troubleshooting

- **"No data found"** — the ticker may be wrong (check it on finance.yahoo.com;
  non-US tickers need their exchange suffix, e.g. `.WA` for Warsaw) or the range
  contains no trading days.
- **Python errors from artisan commands** — make sure `.venv` exists and
  `yfinance` is installed in it; the commands look for
  `.venv/Scripts/python.exe` (Windows) or `.venv/bin/python` (Unix) before
  falling back to `python` on PATH.
- **G / R show "—"** — run `php artisan stocks:fundamentals`; if yfinance has
  fewer than 3 complete fiscal years for the company (common for small caps),
  R falls back to price-based checks on the chart page.
- **Today's candle looks wrong** — intraday data is provisional until the
  session closes; refresh it with
  `php artisan stocks:fetch --symbols=X --start=<today> --force`.
