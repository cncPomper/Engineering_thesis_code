#!/usr/bin/env python3
"""
Asymmetric Donchian Channel (20/10) + ATR (14) trading strategy.

Indicator definitions
---------------------
- Upper band:  highest high of the last 20 completed bars (shifted by 1,
  so the band a bar is judged against never includes that bar itself —
  this prevents repainting).
- Lower band:  lowest low of the last 10 completed bars (shifted by 1).
- ATR(14):     Wilder-smoothed Average True Range, used to place a
  volatility-scaled stop loss at entry: entry -/+ ATR * multiplier.

Signal logic (stop-and-reverse breakout system)
-----------------------------------------------
- Close > upper band  -> LONG breakout  (active signal "BUY")
- Close < lower band  -> SHORT breakout (active signal "SELL")
- Otherwise the previous position is held and the signal is "NEUTRAL".

Usage
-----
As a library:
    from donchian_atr_strategy import get_strategy_signals
    payload = get_strategy_signals("NVDA")

From the CLI (what the Laravel backend calls via Symfony Process):
    python donchian_atr_strategy.py NVDA
    python donchian_atr_strategy.py CDR.WA --lookback 2y --atr-multiplier 2.5 --history 50
    python donchian_atr_strategy.py ANY --csv path/to/ohlcv.csv

Prints a single JSON document to stdout; errors go to stderr with exit code 1.
"""

import argparse
import json
import sys
from dataclasses import dataclass, field
from datetime import datetime, timezone

import numpy as np
import pandas as pd


# ---------------------------------------------------------------------------
# Data sources
# ---------------------------------------------------------------------------

REQUIRED_COLUMNS = ["open", "high", "low", "close"]


def load_from_yfinance(ticker: str, lookback: str = "1y") -> pd.DataFrame:
    """Fetch daily OHLCV history from yfinance and normalize the columns."""
    import yfinance as yf  # imported lazily so CSV mode works without it

    df = yf.Ticker(ticker).history(period=lookback, interval="1d")
    if df.empty:
        raise ValueError(f"yfinance returned no data for '{ticker}'")

    df = df.rename(columns=str.lower)
    df.index = pd.to_datetime(df.index).tz_localize(None)
    df.index.name = "date"
    return df[REQUIRED_COLUMNS + (["volume"] if "volume" in df.columns else [])]


def load_from_csv(path: str) -> pd.DataFrame:
    """
    Load OHLCV bars from a CSV with columns: date, open, high, low, close[, volume].
    Column names are case-insensitive; rows are sorted by date ascending.
    """
    df = pd.read_csv(path)
    df = df.rename(columns=lambda c: c.strip().lower())

    missing = [c for c in ["date"] + REQUIRED_COLUMNS if c not in df.columns]
    if missing:
        raise ValueError(f"CSV is missing required columns: {', '.join(missing)}")

    df["date"] = pd.to_datetime(df["date"])
    return df.set_index("date").sort_index()


# ---------------------------------------------------------------------------
# Strategy
# ---------------------------------------------------------------------------

@dataclass
class DonchianATRStrategy:
    """
    Reusable strategy service: feed it an OHLCV DataFrame (datetime index,
    columns open/high/low/close), get back the same frame enriched with
    bands, ATR, per-bar signals, position state and stop-loss levels.
    """
    dc_upper_period: int = 20
    dc_lower_period: int = 10
    atr_period: int = 14
    atr_multiplier: float = 2.0

    parameters: dict = field(init=False)

    def __post_init__(self):
        self.parameters = {
            "dc_upper_period": self.dc_upper_period,
            "dc_lower_period": self.dc_lower_period,
            "atr_period": self.atr_period,
            "atr_multiplier": self.atr_multiplier,
        }

    # -- indicators ---------------------------------------------------------

    def donchian_bands(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Asymmetric Donchian Channel. shift(1) excludes the current bar from
        its own band, so a breakout is always measured against a channel
        built purely from past data (no repainting).
        """
        upper = df["high"].rolling(self.dc_upper_period).max().shift(1)
        lower = df["low"].rolling(self.dc_lower_period).min().shift(1)
        return pd.DataFrame({"dc_upper": upper, "dc_lower": lower})

    def atr(self, df: pd.DataFrame) -> pd.Series:
        """
        Classic Wilder ATR: SMA of the first `atr_period` true ranges as the
        seed, then atr = (prev_atr * (p - 1) + tr) / p. Matches the canonical
        PHP implementation (App\\Services\\DonchianAtrStrategy) exactly.
        """
        prev_close = df["close"].shift(1)
        true_range = pd.concat(
            [
                df["high"] - df["low"],
                (df["high"] - prev_close).abs(),
                (df["low"] - prev_close).abs(),
            ],
            axis=1,
        ).max(axis=1)

        p = self.atr_period
        values = true_range.to_numpy()
        atr = np.full(len(values), np.nan)
        if len(values) >= p:
            atr[p - 1] = values[:p].mean()
            for i in range(p, len(values)):
                atr[i] = (atr[i - 1] * (p - 1) + values[i]) / p
        return pd.Series(atr, index=df.index)

    # -- signals ------------------------------------------------------------

    def compute(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Vectorized signal computation. Adds columns:
          dc_upper, dc_lower, atr   -- indicator values
          signal                    -- "LONG"/"SHORT" on breakout bars, else "NEUTRAL"
          position                  -- "LONG"/"SHORT"/"FLAT", held between breakouts
          entry_price               -- close of the breakout bar that opened the position
          stop_loss                 -- entry -/+ ATR * multiplier, fixed at entry
        """
        out = df.copy()
        out[["dc_upper", "dc_lower"]] = self.donchian_bands(out)
        out["atr"] = self.atr(out)

        long_break = out["close"] > out["dc_upper"]
        short_break = out["close"] < out["dc_lower"]

        out["signal"] = np.select([long_break, short_break], ["LONG", "SHORT"], "NEUTRAL")

        # Stop-and-reverse position: hold the last breakout direction until
        # the opposite band is broken. Bars before the first breakout are FLAT.
        direction = pd.Series(np.select([long_break, short_break], [1.0, -1.0], np.nan), index=out.index)
        position = direction.ffill().fillna(0)
        out["position"] = position.map({1.0: "LONG", -1.0: "SHORT", 0.0: "FLAT"})

        # A trend keeps re-breaking its band bar after bar, so an entry is only
        # the bar where the position actually flips; entry price and ATR stop
        # are fixed there and carried forward until the next flip.
        is_entry = (position != position.shift(1)) & (position != 0)
        stop_at_entry = out["close"] - position * out["atr"] * self.atr_multiplier
        out["entry_price"] = out["close"].where(is_entry).ffill().where(position != 0)
        out["stop_loss"] = stop_at_entry.where(is_entry).ffill().where(position != 0)

        return out

    # -- API payload --------------------------------------------------------

    def snapshot(self, df: pd.DataFrame, history: int = 30) -> dict:
        """Build the JSON-safe payload the Laravel backend consumes."""
        enriched = self.compute(df)
        last = enriched.iloc[-1]

        active_signal = {"LONG": "BUY", "SHORT": "SELL"}.get(last["signal"], "NEUTRAL")

        history_cols = ["close", "dc_upper", "dc_lower", "atr", "signal", "position", "stop_loss"]
        historical = [
            {"date": idx.strftime("%Y-%m-%d"), **{c: _json_safe(row[c]) for c in history_cols}}
            for idx, row in enriched.tail(history).iterrows()
        ]

        return {
            "generated_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
            "parameters": self.parameters,
            "market_state": {
                "date": enriched.index[-1].strftime("%Y-%m-%d"),
                "close": _json_safe(last["close"]),
                "dc_upper": _json_safe(last["dc_upper"]),
                "dc_lower": _json_safe(last["dc_lower"]),
                "atr": _json_safe(last["atr"]),
            },
            "signal": active_signal,           # action on the latest bar
            "position": last["position"],      # state carried since the last breakout
            "entry_price": _json_safe(last["entry_price"]),
            "stop_loss": _json_safe(last["stop_loss"]),
            "history": historical,
        }


def _json_safe(value):
    """numpy scalars -> Python natives, NaN -> None (JSON null)."""
    if isinstance(value, (np.floating, float)):
        return None if np.isnan(value) else round(float(value), 4)
    if isinstance(value, np.integer):
        return int(value)
    return value


# ---------------------------------------------------------------------------
# Public entry point
# ---------------------------------------------------------------------------

def get_strategy_signals(
    ticker: str,
    lookback: str = "1y",
    history: int = 30,
    atr_multiplier: float = 2.0,
    csv_path: str = None,
) -> dict:
    """
    Run the strategy for a ticker and return a JSON-serializable dict with the
    current market state, active signal, stop loss and recent signal history.
    Data comes from yfinance unless csv_path points at an OHLCV file.
    """
    df = load_from_csv(csv_path) if csv_path else load_from_yfinance(ticker, lookback)

    strategy = DonchianATRStrategy(atr_multiplier=atr_multiplier)
    if len(df) <= strategy.dc_upper_period:
        raise ValueError(
            f"Need more than {strategy.dc_upper_period} bars to compute the "
            f"{strategy.dc_upper_period}-period upper band, got {len(df)}"
        )

    return {"ticker": ticker, **strategy.snapshot(df, history=history)}


def main():
    parser = argparse.ArgumentParser(description="Asymmetric Donchian (20/10) + ATR(14) signals as JSON")
    parser.add_argument("ticker", help="yfinance ticker, e.g. NVDA or CDR.WA")
    parser.add_argument("--lookback", default="1y", help="yfinance period, e.g. 6mo, 1y, 2y (default: 1y)")
    parser.add_argument("--history", type=int, default=30, help="number of recent bars to include (default: 30)")
    parser.add_argument("--atr-multiplier", type=float, default=2.0, help="stop distance in ATRs (default: 2.0)")
    parser.add_argument("--csv", default=None, help="read OHLCV from CSV instead of yfinance")
    args = parser.parse_args()

    try:
        payload = get_strategy_signals(
            args.ticker,
            lookback=args.lookback,
            history=args.history,
            atr_multiplier=args.atr_multiplier,
            csv_path=args.csv,
        )
    except Exception as e:
        print(json.dumps({"error": str(e)}), file=sys.stderr)
        sys.exit(1)

    print(json.dumps(payload))


if __name__ == "__main__":
    main()
