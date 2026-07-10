#!/usr/bin/env python3
import sys
import json
import yfinance as yf
from datetime import datetime, timedelta

def fetch_stock_data(symbol, start_date, end_date):
    """
    Fetch stock data from yfinance for a given symbol and date range.
    Both dates are inclusive (yfinance treats `end` as exclusive, so shift it by one day).
    The symbol is used verbatim as the yfinance ticker, so any exchange works:
    NVDA (Nasdaq), CDR.WA (Warsaw), 3661.TW (Taiwan), BMW.DE (Xetra), ...
    """
    try:
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

def fetch_company_info(symbol):
    """
    Fetch company metadata (name, sector, industry) for visualization.
    Returns None values if yfinance has no info for the ticker.
    """
    try:
        info = yf.Ticker(symbol).info or {}
        return {
            'name': info.get('longName') or info.get('shortName'),
            'sector': info.get('sector'),
            'industry': info.get('industry'),
        }
    except Exception as e:
        print(json.dumps({'error': str(e)}), file=sys.stderr)
        return {'name': None, 'sector': None, 'industry': None}

if __name__ == '__main__':
    if len(sys.argv) < 4:
        print(json.dumps({'info': None, 'data': []}))
        sys.exit(0)

    symbol = sys.argv[1]
    start_date = sys.argv[2]
    end_date = sys.argv[3]

    print(json.dumps({
        'info': fetch_company_info(symbol),
        'data': fetch_stock_data(symbol, start_date, end_date),
    }))
