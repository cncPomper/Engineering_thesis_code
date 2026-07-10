#!/usr/bin/env python3
"""
Fetch fundamental data for a ticker from yfinance and score it with the
GROWTH Investing reliability checklist (see growth_reliability.py).

Usage:  python fetch_fundamentals.py SYMBOL

Prints a JSON object:
  {
    "symbol": ...,
    "years_used": ["2021", ..., "2025"],   # fiscal years fed into the R score
    "revenue_growth": 12.3,                # G: annualized revenue growth, %
    "eps_growth": 15.9,                    # annualized diluted EPS growth, %
    "reliability": {"score": 4, "max_score": 6, "label": "4/6", "checks": {...}}
  }

Fields are null when yfinance does not expose enough statement history
(the reliability score needs at least 3 common fiscal years).
"""

import sys
import json
import yfinance as yf

from growth_reliability import calculate_growth_reliability_score, YEARS, MIN_YEARS


def statement_series(statement, row_name):
    """One statement row as {fiscal_year: value}, NaN periods dropped."""
    try:
        row = statement.loc[row_name]
        return {
            column.strftime('%Y'): float(value)
            for column, value in row.items()
            if value == value  # NaN != NaN
        }
    except Exception:
        return {}


def annualized_growth(values):
    """CAGR in percent from oldest to newest; undefined for non-positive endpoints."""
    if len(values) < 2 or values[0] <= 0 or values[-1] <= 0:
        return None
    return round(((values[-1] / values[0]) ** (1 / (len(values) - 1)) - 1) * 100, 2)


def fetch_fundamentals(symbol):
    ticker = yf.Ticker(symbol)

    try:
        income = ticker.income_stmt
        cash_flow = ticker.cash_flow
        balance = ticker.balance_sheet
    except Exception as e:
        print(json.dumps({'error': str(e)}), file=sys.stderr)
        income = cash_flow = balance = None

    revenue = statement_series(income, 'Total Revenue')
    net_income = statement_series(income, 'Net Income')
    operating_income = statement_series(income, 'Operating Income')
    ebitda = statement_series(income, 'EBITDA')
    eps = statement_series(income, 'Diluted EPS')
    fcf = statement_series(cash_flow, 'Free Cash Flow')
    total_debt = statement_series(balance, 'Total Debt')

    # Reliability needs every metric for the same fiscal year (and revenue > 0
    # so the operating margin is defined)
    common_years = sorted(
        year
        for year in set(revenue) & set(net_income) & set(operating_income)
        & set(ebitda) & set(fcf) & set(total_debt)
        if revenue[year] > 0
    )[-YEARS:]

    reliability = None
    if len(common_years) >= MIN_YEARS:
        try:
            reliability = calculate_growth_reliability_score({
                'revenue': [revenue[y] for y in common_years],
                'net_income': [net_income[y] for y in common_years],
                'free_cash_flow': [fcf[y] for y in common_years],
                'operating_margin': [
                    operating_income[y] / revenue[y] * 100 for y in common_years
                ],
                'total_debt': [total_debt[y] for y in common_years],
                'ebitda': [ebitda[y] for y in common_years],
            })
        except ValueError as e:
            print(json.dumps({'error': str(e)}), file=sys.stderr)

    revenue_years = sorted(revenue)[-YEARS:]
    eps_years = sorted(eps)[-YEARS:]

    return {
        'symbol': symbol,
        'years_used': common_years,
        'revenue_growth': annualized_growth([revenue[y] for y in revenue_years]),
        'eps_growth': annualized_growth([eps[y] for y in eps_years]),
        'reliability': reliability,
    }


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'usage: fetch_fundamentals.py SYMBOL'}))
        sys.exit(1)

    print(json.dumps(fetch_fundamentals(sys.argv[1])))
