import pandas as pd
import numpy as np


def getBeta(series, sl):
  # Acknowledgement: Advances in Financial Machine Learning - Marcos Lopez de Prado
  hl = series[['High', 'Low']].values
  hl = np.log(hl[:, 0] / hl[:, 1]) ** 2
  hl = pd.Series(hl, index=series.index)
  beta = pd.stats.moments.rolling_sum(hl, window=2)
  beta = pd.stats.moments.rolling_mean(beta, window=sl)
  return beta.dropna()

def getGamma(series):
  # Acknowledgement: Advances in Financial Machine Learning - Marcos Lopez de Prado
  h2 = pd.stats.moments.rolling_max(series['High'], windows=2)
  l2 = pd.stats.moments.rolling_min(series['Low'], windows=2)
  gamma = np.log(h2.values / l2.values) ** 2
  gamma = pd.Series(gamma, index=h2.index)
  return gamma.dropna()

def getAlpha(beta, gamma):
  # Acknowledgement: Advances in Financial Machine Learning - Marcos Lopez de Prado
  pass
