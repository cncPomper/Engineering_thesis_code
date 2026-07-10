# Engineering thesis code

## Author: Piotr Kitłowski

Research code and applications for stock market data analysis, forecasting and
visualization.

## Repository structure

| Path | Description |
|---|---|
| [`market-make/`](market-make/) | **Laravel web app** — worldwide stock data pipeline (yfinance), TradingView-style candlestick chart with Donchian Channels, and a stock screener implementing the GROWTH Investing methodology (G/R traffic-light scoring). See its [README](market-make/README.md) for setup and usage. |
| [`src/`](src/) | Time-series forecasting experiments (Jupyter notebooks): ARIMA, Prophet, ARIMA+Prophet hybrid, LSTM, GRU, RNN, CNN, XGBoost, N-BEATS and an ensemble. Reusable model code lives in `src/models/`, shared helpers in `src/utils.py`. |
| [`indicators/`](indicators/) | Custom technical indicators (e.g. Range Filter). |
| [`models/`](models/) | Shared data models (e.g. `Stock`). |
| `helpers.py` | Common helper functions. |
| `Dockerfile` | Container setup for the research environment. |

## TO DO List:

- [x] YFinance (downloading data) — done in `market-make` (`stocks:fetch`, any exchange)
- [x] Fundamental analysis (GROWTH screener: revenue growth 5Y, reliability score)
- [ ] LLM Agent to search information about the companies (C/WK, C/K, ...)
- [ ] API from broker (Bossa), Revolut or BlackBull
- [ ] DB NoSQL
- [ ] Sentiment Analysis
- [ ] Entity Recognition & Linking
- [ ] Topic Modeling and Classification

### Sources and motivation

* [Advances in Financial Machine Learning — Marcos Lopez de Prado](https://lubimyczytac.pl/ksiazka/4993183/advances-in-financial-machine-learning)
* [GROWTH Investing methodology](https://growthinvesting.net/features) — basis for the screener in `market-make`
* [TradingView Lightweight Charts](https://github.com/tradingview/lightweight-charts) — charting library used in `market-make`

TBC...
