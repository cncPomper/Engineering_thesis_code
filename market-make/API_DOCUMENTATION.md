# Market-Make Documentation

## Overview

Laravel-based stock market pipeline for Warsaw Stock Exchange (WSE) and US markets. Fetches daily OHLC data, computes Donchian Channel + ATR signals, and sends Discord alerts. Data is stored in Supabase (PostgreSQL).

---

## Quick Start

```bash
# Start local server
php artisan serve --port=8000

# Test API
curl "http://localhost:8000/api/stocks/range?start=01.04.2026&end=05.07.2026&timeframe=1D&symbol=MOC"
```

---

## Architecture

```
GitHub Actions (cron, weekdays)
        │
        ├─ 16:15 UTC ─→ stocks:fetch --symbols=db:.WA
        │               signals:compute
        │               (WSE close pipeline)
        │
        └─ 21:30 UTC ─→ stocks:fetch --symbols=db
                        signals:compute
                        (Full pipeline — all markets)
                                │
                                ▼
                    Supabase (PostgreSQL)
                    ┌─────────────────────────────┐
                    │  stocks    signals    alerts │
                    └──────────────┬──────────────┘
                                   │ BEFORE UPDATE trigger
                                   ▼
                         compute_signal_alert()
                         (position change / stop hit)
                                   │ INSERT into alerts
                                   ▼
                           notify_discord()
                           (AFTER INSERT trigger)
                                   │ net.http_post
                                   ▼
                             Discord webhook
```

---

## Database

**Provider:** Supabase (PostgreSQL)

### Tables

#### `stocks`
| Column | Type | Description |
|--------|------|-------------|
| `id` | int8 | Primary key |
| `symbol` | varchar | Ticker symbol |
| `date` | date | Trading date |
| `open/high/low/close` | float8 | OHLC prices |
| `volume` | int8 | Trading volume |

#### `signals`
| Column | Type | Description |
|--------|------|-------------|
| `symbol` | varchar | Unique — one row per symbol |
| `date` | date | Latest bar computed from |
| `close` | float8 | Latest close price |
| `dc_upper/dc_lower` | float8 | Donchian Channel bands |
| `atr` | float8 | ATR(14) value |
| `signal` | varchar | Bar signal: LONG / SHORT / NEUTRAL |
| `position` | varchar | Carried state: LONG / SHORT / FLAT |
| `entry_date/entry_price` | date/float8 | Entry details |
| `stop_loss` | float8 | Current stop loss level |
| `stop_hit` | bool | Whether stop was triggered |
| `alerted_position` | varchar | Last position Discord was told about |
| `stop_alerted_for` | date | Entry date whose stop-hit was alerted |

#### `alerts`
| Column | Type | Description |
|--------|------|-------------|
| `symbol` | text | Ticker symbol |
| `close` | numeric(10,4) | Price at alert time |
| `triggered_at` | timestamptz | When alert fired |
| `message` | text | Discord embed JSON payload |

RLS: enabled — no public access (service role only).

#### `companies`
Fundamental data per symbol: sector, industry, revenue growth, EPS growth, reliability score.

---

## Signal Strategy

**Donchian Channel (20/10) + ATR(14)**

- **LONG entry:** close breaks above 20-period upper band
- **SHORT entry:** close breaks below 20-period lower band
- **Stop loss:** entry price ± 2× ATR(14)
- **Exit:** 10-period opposite band breach or stop hit

---

## Supabase Triggers

### `on_signal_update` (BEFORE UPDATE on `signals`)
Runs `compute_signal_alert()` — checks two conditions:

1. **Position change:** `position IS DISTINCT FROM alerted_position`
   → inserts alert, sets `alerted_position = position`

2. **Stop hit:** `stop_hit = true AND stop_alerted_for IS DISTINCT FROM entry_date`
   → inserts alert, sets `stop_alerted_for = entry_date`

### `on_alert_insert` (AFTER INSERT on `alerts`)
Runs `notify_discord()` — reads webhook URL from Supabase Vault and POSTs a Discord embed via `net.http_post`.

**Discord embed format:**
```
🟢 BUY CPS.WA                    (green border)
Upper band breakout on 2026-07-20 — Donchian(20/10) + ATR(14)
Price     Stop loss    ATR(14)
17.00     15.79        0.60
```

---

## Pipeline Commands

```bash
# Fetch prices for all symbols in DB
php artisan stocks:fetch --symbols=db

# Fetch only WSE symbols
php artisan stocks:fetch --symbols=db:.WA

# Fetch specific symbols
php artisan stocks:fetch --symbols=MOC.WA,AMB.WA

# Recompute all signals
php artisan signals:compute
```

---

## GitHub Actions Schedule

Two workflows in `.github/workflows/`:

| Workflow | Cron (UTC) | Commands |
|----------|-----------|----------|
| `scheduler-wse.yml` | `15 16 * * 1-5` | stocks:fetch (.WA) → signals:compute |
| `scheduler-full.yml` | `30 21 * * 1-5` | stocks:fetch (all) → signals:compute |

Both support `workflow_dispatch` for manual runs from the GitHub UI.

**Required GitHub Secrets:**

| Secret | Source |
|--------|--------|
| `APP_KEY` | Local `.env` → `APP_KEY=base64:...` |
| `DB_HOST` | Supabase → Project Settings → Database → Host |
| `DB_PASSWORD` | Supabase → Project Settings → Database → Password |
| `DISCORD_WEBHOOK_URL` | Discord → Server Settings → Integrations → Webhooks |

---

## Discovering Available Symbols

Query the Supabase REST API to see which symbols exist in the database before calling the stock API.

**Get all symbols:**
```bash
curl "https://YOUR_PROJECT_REF.supabase.co/rest/v1/companies?select=symbol" \
  -H "apikey: YOUR_ANON_KEY"
```

**Filter WSE symbols only (.WA):**
```bash
curl "https://YOUR_PROJECT_REF.supabase.co/rest/v1/companies?select=symbol&symbol=like.*WA" \
  -H "apikey: YOUR_ANON_KEY"
```

**Response:**
```json
[
  { "symbol": "MOC.WA" },
  { "symbol": "CDR.WA" },
  { "symbol": "PKN.WA" }
]
```

Find your `YOUR_PROJECT_REF` and `YOUR_ANON_KEY` in:
**Supabase Dashboard → Project Settings → API**

---

## Stock API Endpoint

### `GET /api/stocks/range`

Returns OHLC data with grid visualization for interactive charts.

**Query Parameters:**

| Parameter | Required | Format | Values | Default |
|-----------|----------|--------|--------|---------|
| `start` | ✓ | `d.m.Y` | `01.01.2026` | - |
| `end` | ✓ | `d.m.Y` | `05.07.2026` | - |
| `timeframe` | ✗ | enum | `1D`, `1W`, `1M` | `1D` |
| `symbol` | ✗ | string | any DB symbol | `MOC` |

**Example Requests:**

```bash
# Daily data
curl "http://localhost:8000/api/stocks/range?start=01.04.2026&end=05.07.2026&timeframe=1D&symbol=MOC"

# Weekly aggregation
curl "http://localhost:8000/api/stocks/range?start=01.04.2026&end=05.07.2026&timeframe=1W&symbol=AMB"

# Monthly aggregation
curl "http://localhost:8000/api/stocks/range?start=01.04.2026&end=05.07.2026&timeframe=1M&symbol=MOC"
```

**Response Structure:**

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
      "data": []
    }
  ]
}
```

**Timeframe aggregation:**
- `1D` — raw daily OHLC from DB
- `1W` — open: first day, high/low: max/min, close: last day, volume: sum
- `1M` — same logic per calendar month

**Grid:** 10 daily candles per cell for "click to zoom" chart UI.

---

## Supported Symbols

**WSE (Warsaw Stock Exchange):**
```
PKN.WA,PKO.WA,PEO.WA,PZU.WA,KGH.WA,CDR.WA,ALE.WA,LPP.WA,ALR.WA,ING.WA,MBK.WA,MIL.WA,BDX.WA,BFT.WA,BHW.WA,CAR.WA,DOM.WA,DNP.WA,ENE.WA,EUR.WA,GPW.WA,JSW.WA,KRU.WA,SPL.WA,TPE.WA,11B.WA,ABS.WA,ACP.WA,ACT.WA,ATC.WA,ATT.WA,BCC.WA,BOS.WA,BRS.WA,CIG.WA,CLN.WA,CMP.WA,COG.WA,CPF.WA,CPS.WA,CRM.WA,CTX.WA,EAT.WA,ECH.WA,ELT.WA,ENA.WA,ERB.WA,FTE.WA,GTC.WA,HUG.WA,ICE.WA,IFI.WA,KCI.WA,LVC.WA,MAB.WA,MCI.WA,MGT.WA,NEU.WA,OPL.WA,PCO.WA,PGE.WA,ZAB.WA,TEX.WA,MDV.WA,KTY.WA,EBP.WA
```

**US (S&P 500):**
```
A,AAL,AAPL,ABBV,ABT,ACGL,ACN,ADBE,ADI,ADM,ADP,ADSK,AEE,AEP,AES,AFL,AIG,AIZ,AJG,AKAM,ALB,ALGN,ALK,ALL,ALLE,AMAT,AMCR,AMD,AME,AMGN,AMP,AMT,AMZN,ANET,ANSS,AON,AOS,APA,APD,APH,APTV,ARE,ARES,ATO,AVB,AVGO,AVY,AWK,AXON,AXP,AZO,BA,BAC,BALL,BAX,BBWI,BBY,BDX,BEN,BF.B,BG,BIIB,BK,BKNG,BKR,BLDR,BLK,BMY,BR,BRK.B,BRO,BSX,BWA,BX,BXP,C,CAG,CAH,CARR,CAT,CB,CBOE,CBRE,CCI,CCJ,CCL,CDNS,CDW,CE,CEG,CF,CFG,CHD,CHRW,CHTR,CI,CINF,CL,CLX,CME,CMG,CMI,CMS,CNC,CNP,COHR,COIN,COO,COP,COR,COST,CPAY,CPB,CPRT,CPT,CRL,CRM,CRWD,CSCO,CSGP,CSX,CTAS,CTRA,CTSH,CTVA,CVS,CVX,CZR,D,DAL,DD,DE,DECK,DELL,DFS,DG,DGX,DHI,DHR,DIS,DLR,DLTR,DOC,DOV,DOW,DPZ,DRI,DTE,DUK,DVA,DVN,DXCM,EA,EBAY,ECL,ED,EFX,EG,EIX,EL,ELV,EME,EMR,ENPH,EOG,EPAM,EQIX,EQR,EQT,ERIE,ES,ESS,ETN,ETR,EVRG,EW,EXC,EXPD,EXPE,EXR,F,FANG,FAST,FCX,FDS,FDX,FE,FFIV,FI,FICO,FIS,FITB,FMC,FOX,FOXA,FRT,FTNT,FTV,GD,GE,GEV,GILD,GIS,GL,GLW,GM,GNRC,GOOG,GOOGL,GPC,GPK,GRMN,GS,GWW,HAL,HAS,HBAN,HCA,HD,HES,HIG,HII,HLT,HOLX,HON,HPE,HPQ,HRL,HSIC,HST,HSY,HUBB,HUM,HWM,IBM,ICE,IDXX,IEX,IFF,INCY,INTC,INTU,INVH,IP,IPG,IQV,IR,IRM,ISRG,IT,ITW,J,JBHT,JBL,JCI,JKHY,JNJ,JPM,K,KDP,KEY,KEYS,KHC,KIM,KKR,KLAC,KMB,KMI,KMX,KO,KR,KVUE,L,LDOS,LEN,LH,LHX,LIN,LITE,LKQ,LLY,LMT,LNT,LOW,LRCX,LULU,LUV,LVS,LW,LYB,LYV,MA,MAA,MAR,MAS,MCD,MCHP,MCK,MCO,MDLZ,MDT,MET,META,MGM,MHK,MKC,MKTX,MLM,MMC,MMM,MNST,MO,MOH,MOS,MPC,MPWR,MRK,MRNA,MS,MSCI,MSFT,MSI,MTB,MTCH,MTD,MU,NCLH,NDAQ,NDSN,NEE,NEM,NFLX,NI,NKE,NOC,NOW,NRG,NSC,NTAP,NTRS,NUE,NVDA,NVR,NWS,NWSA,NXPI,O,ODFL,OKE,OMC,ON,ORCL,ORLY,OTIS,OXY,PANW,PAYX,PAYC,PCAR,PCG,PEG,PEP,PFE,PFG,PG,PGR,PH,PHM,PKG,PLD,PLTR,PM,PNC,PNR,PNW,PODD,POOL,PPG,PPL,PRU,PSA,PSX,PTC,PWR,PYPL,QCOM,QRVO,RCL,REG,REGN,RF,RMD,ROK,ROL,ROP,ROST,RSG,RTX,RVTY,SBAC,SBUX,SCHW,SHW,SJM,SLB,SMCI,SNA,SNPS,SO,SPG,SPGI,SRE,STE,STLD,STT,STX,STZ,SWK,SWKS,SYK,SYF,SYY,T,TAP,TDG,TDY,TECH,TEL,TER,TFC,TFX,TGT,TJX,TMO,TMUS,TPR,TRGP,TRMB,TROW,TRV,TSCO,TSLA,TSN,TYL,TXN,TXT,UAL,UBER,UDR,UHS,ULTA,UNH,UNP,UPS,URI,USB,V,VICI,VLO,VLTO,VMC,VRSN,VRSK,VRT,VRTX,VST,VTR,VZ,WAB,WAT,WBA,WBD,WDC,WEC,WELL,WFC,WMB,WMT,WRB,WST,WTW,WY,WYNN,XEL,XOM,XYL,YUM,ZBRA,ZBH,ZTS
```

---

## Troubleshooting

**"No data found"**
→ Run `php artisan stocks:fetch --symbols=SYMBOL` to populate data

**DB connection errors**
→ Check `.env` has correct Supabase credentials (`DB_CONNECTION=pgsql`)

**Discord alerts not sending**
→ Verify `pg_net` extension is enabled: `SELECT * FROM pg_extension WHERE extname = 'pg_net';`
→ Check webhook URL in Vault: `SELECT name FROM vault.decrypted_secrets;`
→ Inspect HTTP responses: `SELECT id, status_code, error_msg FROM net._http_response ORDER BY created DESC LIMIT 5;`

**GitHub Actions not triggering**
→ Verify secrets are set in repo Settings → Secrets and variables → Actions
→ Manually trigger via Actions tab → workflow → Run workflow
