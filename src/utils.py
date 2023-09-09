import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
from statsmodels.tsa.stattools import adfuller
from sklearn.metrics import mean_squared_error as mse
from sklearn.metrics import mean_absolute_error, mean_absolute_percentage_error

def test_stationarity(timeseries, window_size=250):

    #Determing rolling statistics
    rolmean = timeseries.rolling(window_size).mean()
    rolstd = timeseries.rolling(window_size).std()

    #Plot rolling statistics:
    fig = plt.figure(figsize=(12, 8))
    orig = plt.plot(timeseries, color='blue',label='Original')
    mean = plt.plot(rolmean, color='red', label='Rolling Mean')
    std = plt.plot(rolstd, color='black', label = 'Rolling Std')
    plt.legend(loc='best')
    plt.title('Rolling Mean & Standard Deviation')
    plt.show()

    #Perform Dickey-Fuller test:
    print('Results of Dickey-Fuller Test:')
    dftest = adfuller(timeseries)
    if (dftest[1] <= 0.05) & (dftest[4]['5%'] > dftest[0]):
        print("\u001b[32mStationary\u001b[0m")
    else:
        print("\x1b[31mNon-stationary\x1b[0m")

    dfoutput = pd.Series(dftest[0:4], index=['Test Statistic','p-value','#Lags Used','Number of Observations Used'])
    for key,value in dftest[4].items():
        dfoutput['Critical Value (%s)'%key] = value

    print(dfoutput)

def my_rmse(x,y):
    return(np.round( np.sqrt(mse(x,y)) ,4))

def create_dataset(df, look_back, look_ahead):
    xdat, ydat = [], []
    for i in range(len(df) - look_back -look_ahead):
        xdat.append(df[i:i+ look_back ,0])
        ydat.append(df[i+ look_back : i + look_back + look_ahead,0])
    xdat, ydat = np.array(xdat), np.array(ydat).reshape(-1,look_ahead)
    return xdat, ydat

def prepare_split(xdat, ydat, cutoff = 5000, timesteps = 50):
    xtrain, xvalid = xdat[:cutoff,:], xdat[cutoff:,]
    ytrain, yvalid = ydat[:cutoff,:], ydat[cutoff:,]

    # reshape into [batch size, time steps, dimensionality]
    xtrain = xtrain.reshape(-1, timesteps, 1)
    xvalid = xvalid.reshape(-1, timesteps, 1)

    return xtrain, ytrain, xvalid, yvalid

def make_df_from(df):
  df_out = df[['Date', 'Close']].copy()
  df_out['Date'] = pd.to_datetime(df_out['Date'])
  df_out.set_index('Date', inplace=True)
  return df_out

def assign_values(df, dataset_name, y_pred, y_valid_inversed, period):

  mae = mean_absolute_error(y_pred, y_valid_inversed)
  mape = mean_absolute_percentage_error(y_pred, y_valid_inversed)
  rmse = my_rmse(y_pred, y_valid_inversed)

  mae = np.round(mae ,4)
  mape = np.round(mape ,4)

#   print('RMSE LSTM: ' + str(rmse))
#   print('MAE LSTM: ' + str(mae))
#   print('MAPE LSTM: ' + str(mape*100) + '%')

  df.loc[dataset_name, 'MAE'][period] = mae
  df.loc[dataset_name, 'MAPE'][period] = mape
  df.loc[dataset_name, 'RMSE'][period] = rmse

  return df