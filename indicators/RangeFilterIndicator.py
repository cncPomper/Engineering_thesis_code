"""
Range Filter Buy and Sell 5min Indicator
Original Script > @DonovanWall
Adapted Version > @guikroth
Updated PineScript to version 5 > @tvenn
Python conversion of TradingView Pine Script

This indicator identifies buy and sell signals based on range filtering. 
Adjust parameters for other assets.
"""

import pandas as pd
import numpy as np
from typing import Tuple, List, Dict


class RangeFilterIndicator:  
    """Range Filter Buy and Sell Indicator for Technical Analysis"""
    
    def __init__(self, sampling_period: int = 100, range_multiplier: float = 3.0):
        """
        Initialize the Range Filter Indicator
        
        Args:
            sampling_period: Period for sampling (default: 100)
            range_multiplier: Multiplier for range (default: 3.0)
        """
        self.sampling_period = sampling_period
        self.range_multiplier = range_multiplier

        # Color definitions (RGB tuples)
        self.up_color = (255, 255, 255)      # White
        self.mid_color = (144, 191, 249)     # Light blue
        self.down_color = (0, 0, 255)        # Blue
    
    def ema(self, data: pd.Series, period: int) -> pd.Series:
        """Calculate Exponential Moving Average"""
        return data.ewm(span=period, adjust=False).mean()
    
    def smooth_average_range(self, source: pd.Series) -> pd.Series:
        """
        Calculate Smooth Average Range
        
        Args:
            source: Price source (typically close prices)
            
        Returns:
            Smoothed range values
        """
        wper = self.sampling_period * 2 - 1
        price_diff = (source - source.shift(1)).abs()
        avrng = self.ema(price_diff, self.sampling_period)
        smrng = self.ema(avrng, wper) * self.range_multiplier
        
        return smrng
    
    def range_filter(self, source: pd.Series, smooth_range: pd.Series) -> pd.Series:
        """
        Apply Range Filter to price data
        
        Args:
            source: Price source
            smooth_range: Smoothed range values
            
        Returns:
            Filtered price values
        """
        filter = source.copy()

        for idx in range(1, len(filter)):
            previous_filter = filter.iloc[i - 1]
            current_source = source.iloc[i]
            current_range = smooth_range.iloc[i]

            if current_source > previous_filter:
                filter.iloc[i] = max(current_src - current_range, previous_filter)
            else:
                val = current_src + current_range
                filter.iloc[i] = previous_filter if val > previous_filter else val

        return filter
    
    def calculate_direction(self, filt: pd.Series) -> Tuple[pd.Series, pd.Series]:
        """
        Calculate upward and downward directions
        
        Args:
            filt: Filtered price values
            
        Returns:
            Tuple of upward and downward series
        """
        upward = pd.Series(0.0, index=filt.index)
        downward = pd.Series(0.0, index=filt.index)

        for i in range(1, len(filt)):
            if filt.iloc[i] > filt.iloc[i - 1]:
                upward.iloc[i] = upward.iloc[i - 1] + 1
                downward.iloc[i] = 0
            elif filt.iloc[i] < filt.iloc[i - 1]:
                downward.iloc[i] = downward.iloc[i - 1] + 1
                upward.iloc[i] = 0
            else:
                upward.iloc[i] = upward.iloc[i - 1]
                downward.iloc[i] = downward.iloc[i - 1]
        
        return upward, downward
    
    def analyze(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Perform complete analysis on OHLCV data
        
        Args:
            df: DataFrame with columns ['open', 'high', 'low', 'close', 'volume']
            
        Returns:
            DataFrame with added indicator columns
        """
        # Create a copy to avoid modifying original
        result = df.copy()

        # Source (using close price)
        source = result['close']

        # Calculate smooth range
        result['smooth_range'] = self.smooth_average_range(source)
          
        # Apply range filter
        result['filter'] = self.range_filter(source, result['smooth_range'])

        # Calculate direction
        result['upward'], result['downward'] = self.calculate_direction(result['filter'])
        def calculate_bar_color(row):
            pass

                 
