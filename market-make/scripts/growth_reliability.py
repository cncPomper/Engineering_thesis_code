#!/usr/bin/env python3
"""
GROWTH Investing "R" (Reliability / Risk Analysis) score.

Implements the 5-year fundamental reliability checklist used by the GROWTH
Investing methodology (https://growthinvesting.net). The score expresses how
financially stable and *predictable* a company has been over the last five
fiscal years: each of six independent tests awards one point, giving a final
score between 0/6 and 6/6.

The six criteria
----------------
1. Revenue Consistency        - YoY revenue growth > 0% in all 4 transitions.
2. Net Income Stability       - no net losses AND no YoY income drop > 15%.
3. Positive FCF Generation    - free cash flow > 0 in every year.
4. Operating Margin           - margin(Y5) >= margin(Y1) OR stdev(margin) < 2pp.
5. Debt Control               - Debt-to-EBITDA strictly below 3.0x every year.
6. Growth Linearity (R2)      - linear fit of revenue has R2 >= 0.85.

This module only needs numpy (already required by yfinance in this project).
"""

import numpy as np

YEARS = 5                    # ideal window; see MIN_YEARS for the accepted minimum
MIN_YEARS = 3                # yfinance often exposes only 3-4 annual periods
NET_INCOME_MAX_DROP = 0.15   # criterion 2: max tolerated YoY net income drop
MARGIN_STDEV_LIMIT = 2.0     # criterion 4: percentage points
DEBT_EBITDA_LIMIT = 3.0      # criterion 5
R2_THRESHOLD = 0.85          # criterion 6


def calculate_growth_reliability_score(data):
    """
    Calculate the GROWTH Investing reliability score (0/6 .. 6/6).

    Parameters
    ----------
    data : dict or pandas.DataFrame
        Consecutive fiscal years of fundamentals, oldest first — ideally 5
        years, minimum 3 (all series must have the same length).
        Required keys / columns (each a sequence of floats):

        - ``revenue``           total revenue per year
        - ``net_income``        net income per year
        - ``free_cash_flow``    free cash flow per year
        - ``operating_margin``  operating margin per year, in percent (e.g. 31.5)

        Debt input, either:

        - ``debt_to_ebitda``    pre-calculated ratio per year, or
        - ``total_debt`` and ``ebitda`` (the ratio is derived per year)

    Returns
    -------
    dict
        ``score``     int, number of criteria passed (0..6)
        ``max_score`` int, always 6
        ``label``     str, e.g. ``"4/6"``
        ``checks``    dict of criterion name -> {'passed': bool, 'details': str}

    Raises
    ------
    ValueError
        If a required series is missing or does not contain exactly 5 values.

    Notes on edge cases
    -------------------
    - Debt-to-EBITDA with EBITDA <= 0: a debt-free year passes (there is
      nothing to service), otherwise the year fails (debt cannot be serviced
      from non-positive earnings). Division by zero is never performed.
    - R2 with zero revenue variance (perfectly flat revenue) is defined as
      1.0 - the series is exactly linear, criterion 1 rejects stagnation.
    """
    series = _extract_series(data)

    checks = {
        'Revenue Consistency': _check_revenue_consistency(series['revenue']),
        'Net Income Stability': _check_net_income_stability(series['net_income']),
        'Positive FCF Generation': _check_positive_fcf(series['free_cash_flow']),
        'Operating Margin Sustainability': _check_operating_margin(series['operating_margin']),
        'Debt Control': _check_debt_control(series['debt_to_ebitda']),
        'Growth Linearity (R2)': _check_growth_linearity(series['revenue']),
    }

    score = sum(1 for check in checks.values() if check['passed'])

    return {
        'score': score,
        'max_score': 6,
        'label': f'{score}/6',
        'checks': checks,
    }


def _extract_series(data):
    """
    Normalize the input (dict or DataFrame) into plain float lists and derive
    the Debt-to-EBITDA series if it was not supplied pre-calculated.
    """
    # pandas.DataFrame quacks with a `columns` attribute; avoid a hard import
    if hasattr(data, 'columns'):
        data = {column: data[column].tolist() for column in data.columns}

    series = {}
    years = None
    for key in ('revenue', 'net_income', 'free_cash_flow', 'operating_margin'):
        series[key] = _validate_series(data, key, years)
        years = years or len(series[key])

    if 'debt_to_ebitda' in data:
        series['debt_to_ebitda'] = _validate_series(data, 'debt_to_ebitda', years)
    elif 'total_debt' in data and 'ebitda' in data:
        total_debt = _validate_series(data, 'total_debt', years)
        ebitda = _validate_series(data, 'ebitda', years)
        series['debt_to_ebitda'] = [
            _safe_debt_to_ebitda(debt, earnings)
            for debt, earnings in zip(total_debt, ebitda)
        ]
    else:
        raise ValueError(
            "Provide either 'debt_to_ebitda' or both 'total_debt' and 'ebitda'"
        )

    return series


def _validate_series(data, key, expected_length=None):
    if key not in data:
        raise ValueError(f"Missing required series '{key}'")

    values = [float(v) for v in data[key]]
    if expected_length is not None and len(values) != expected_length:
        raise ValueError(
            f"Series '{key}' must contain {expected_length} values "
            f"(same as the other series), got {len(values)}"
        )
    if not MIN_YEARS <= len(values) <= YEARS:
        raise ValueError(
            f"Series '{key}' must contain {MIN_YEARS}-{YEARS} values, got {len(values)}"
        )

    return values


def _safe_debt_to_ebitda(total_debt, ebitda):
    """
    Debt-to-EBITDA for one year, without dividing by zero.

    EBITDA <= 0 means debt cannot be serviced from earnings: return +inf so the
    year fails criterion 5 - unless the company carries no debt at all, which
    is trivially safe (ratio 0).
    """
    if ebitda <= 0:
        return 0.0 if total_debt == 0 else float('inf')
    return total_debt / ebitda


def _check_revenue_consistency(revenue):
    """Criterion 1: YoY revenue growth positive in all 4 transitions."""
    drops = [
        f'Y{i}->Y{i + 1}'
        for i in range(1, len(revenue))
        if revenue[i] <= revenue[i - 1]
    ]
    return {
        'passed': not drops,
        'details': 'Revenue grew every year'
        if not drops else f"Revenue flat or shrinking in: {', '.join(drops)}",
    }


def _check_net_income_stability(net_income):
    """Criterion 2: no net losses and no YoY net income drop above 15%."""
    losses = [f'Y{i + 1}' for i, income in enumerate(net_income) if income <= 0]
    if losses:
        return {
            'passed': False,
            'details': f"Net loss in: {', '.join(losses)}",
        }

    # All values are > 0 here, so the relative drop is well-defined
    big_drops = []
    for i in range(1, len(net_income)):
        drop = (net_income[i - 1] - net_income[i]) / net_income[i - 1]
        if drop > NET_INCOME_MAX_DROP:
            big_drops.append(f'Y{i}->Y{i + 1} ({drop:.0%})')

    return {
        'passed': not big_drops,
        'details': 'Profitable every year with stable income'
        if not big_drops else f"Income drop above {NET_INCOME_MAX_DROP:.0%} in: {', '.join(big_drops)}",
    }


def _check_positive_fcf(free_cash_flow):
    """Criterion 3: free cash flow positive in all 5 individual years."""
    negative = [f'Y{i + 1}' for i, fcf in enumerate(free_cash_flow) if fcf <= 0]
    return {
        'passed': not negative,
        'details': 'Positive FCF every year'
        if not negative else f"Non-positive FCF in: {', '.join(negative)}",
    }


def _check_operating_margin(operating_margin):
    """
    Criterion 4: margin in Y5 at least as high as in Y1, OR margin stdev
    below 2 percentage points (population standard deviation).
    """
    improved = operating_margin[-1] >= operating_margin[0]
    stdev = float(np.std(operating_margin))
    stable = stdev < MARGIN_STDEV_LIMIT

    if improved:
        details = (
            f'Margin sustained: {operating_margin[0]:.1f}% -> {operating_margin[-1]:.1f}%'
        )
    elif stable:
        details = f'Margin declined but stable (stdev {stdev:.2f}pp < {MARGIN_STDEV_LIMIT}pp)'
    else:
        details = (
            f'Margin fell {operating_margin[0]:.1f}% -> {operating_margin[-1]:.1f}% '
            f'with stdev {stdev:.2f}pp'
        )

    return {'passed': improved or stable, 'details': details}


def _check_debt_control(debt_to_ebitda):
    """Criterion 5: Debt-to-EBITDA strictly below 3.0x in every year."""
    breaches = [
        f'Y{i + 1} ({f"{ratio:.1f}x" if ratio != float("inf") else "negative EBITDA"})'
        for i, ratio in enumerate(debt_to_ebitda)
        if ratio >= DEBT_EBITDA_LIMIT
    ]
    return {
        'passed': not breaches,
        'details': f'Debt/EBITDA below {DEBT_EBITDA_LIMIT}x every year'
        if not breaches else f"Debt/EBITDA at or above {DEBT_EBITDA_LIMIT}x in: {', '.join(breaches)}",
    }


def _check_growth_linearity(revenue):
    """
    Criterion 6: fit revenue = a*year + b over the 5 years and require
    R2 >= 0.85, i.e. a highly predictable, non-cyclical growth path.
    """
    x = np.arange(len(revenue), dtype=float)
    y = np.asarray(revenue, dtype=float)

    ss_tot = float(np.sum((y - y.mean()) ** 2))
    if ss_tot == 0.0:
        # Perfectly flat revenue is exactly linear (criterion 1 penalizes it)
        r_squared = 1.0
    else:
        slope, intercept = np.polyfit(x, y, 1)
        residuals = y - (slope * x + intercept)
        r_squared = 1.0 - float(np.sum(residuals ** 2)) / ss_tot

    return {
        'passed': r_squared >= R2_THRESHOLD,
        'details': f'Revenue linearity R2 = {r_squared:.3f} '
                   f'({">=" if r_squared >= R2_THRESHOLD else "<"} {R2_THRESHOLD})',
    }


if __name__ == '__main__':
    def _print_report(title, result):
        print(f'\n{title}: {result["label"]}')
        for name, check in result['checks'].items():
            mark = 'PASS' if check['passed'] else 'FAIL'
            print(f'  [{mark}] {name}: {check["details"]}')

    # A stable compounder (Microsoft/Visa-like): steady growth, fat stable
    # margins, always profitable, low leverage -> expected 6/6
    stable_stock = {
        'revenue': [143.0, 168.1, 198.3, 211.9, 245.1],
        'net_income': [44.3, 61.3, 72.7, 72.4, 88.1],
        'free_cash_flow': [45.2, 56.1, 65.1, 59.5, 74.1],
        'operating_margin': [37.0, 41.6, 42.1, 41.8, 44.6],
        'total_debt': [63.3, 58.1, 49.8, 47.2, 44.9],
        'ebitda': [68.4, 85.1, 100.2, 105.1, 133.6],
    }

    # A cyclical commodity-like business: revenue whipsaws, loss years,
    # negative FCF, collapsing margin - only its balance sheet is clean
    # -> expected 1/6 (Debt Control)
    cyclical_stock = {
        'revenue': [100.0, 140.0, 90.0, 130.0, 85.0],
        'net_income': [5.0, 20.0, -10.0, 15.0, -5.0],
        'free_cash_flow': [-2.0, 10.0, -8.0, 6.0, -4.0],
        'operating_margin': [8.0, 15.0, 3.0, 12.0, 2.0],
        'total_debt': [10.0, 10.0, 10.0, 10.0, 10.0],
        'ebitda': [20.0, 28.0, 10.0, 25.0, 9.0],
    }

    _print_report('Stable compounder', calculate_growth_reliability_score(stable_stock))
    _print_report('Cyclical stock', calculate_growth_reliability_score(cyclical_stock))
