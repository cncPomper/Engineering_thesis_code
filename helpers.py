import pandas as pd


def getBeta(series, sl):
  # Acknowledgement: Advances in Financial Machine Learning - Marcos Lopez de Prado
  hl = series[['High', 'Low']].values
  hl = np.log(hl[:, 0] / hl[:, 1]) ** 2
  hl = pd.Series(hl, index=series.index)
  beta = pd.stats.moments.rolling_sum(hl, window=2)
  beta = pd.stats.moments.rolling_mean(beta, window=sl)
  return beta.dropna()
