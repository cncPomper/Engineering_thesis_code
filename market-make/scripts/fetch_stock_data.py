#!/usr/bin/env python3
import sys
import json
import yfinance as yf
from datetime import datetime, timedelta

def fetch_stock_data(symbol, start_date, end_date):
    """
    Fetch stock data from yfinance for a given symbol and date range.
    Both dates are inclusive (yfinance treats `end` as exclusive, so shift it by one day).
    For GPW stocks, append .WA suffix (Warsaw exchange)
    """
    try:
        # Add .WA suffix for Polish stocks (GPW exchange)
        symbol = f"{symbol}.WA"

        end_exclusive = (datetime.strptime(end_date, '%Y-%m-%d') + timedelta(days=1)).strftime('%Y-%m-%d')

        ticker = yf.Ticker(symbol)
        df = ticker.history(start=start_date, end=end_exclusive)

        if df.empty:
            return []

        result = []
        for date, row in df.iterrows():
            result.append({
                'date': date.strftime('%Y-%m-%d'),
                'open': float(row['Open']),
                'high': float(row['High']),
                'low': float(row['Low']),
                'close': float(row['Close']),
                'volume': int(row['Volume']),
            })

        return result
    except Exception as e:
        print(json.dumps({'error': str(e)}), file=sys.stderr)
        return []

if __name__ == '__main__':
    if len(sys.argv) < 4:
        print(json.dumps([]))
        sys.exit(0)

    symbol = sys.argv[1]
    start_date = sys.argv[2]
    end_date = sys.argv[3]

    data = fetch_stock_data(symbol, start_date, end_date)
    print(json.dumps(data))
