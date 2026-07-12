<?php

namespace App\Services;

/**
 * Asymmetric Donchian Channel (20/10) + ATR (14) breakout strategy.
 *
 * - Upper band: highest high of the previous 20 bars (shifted by 1, so a bar
 *   is never judged against a channel that includes itself — no repainting).
 * - Lower band: lowest low of the previous 10 bars (shifted by 1).
 * - ATR(14), classic Wilder smoothing: SMA of the first 14 true ranges,
 *   then atr = (prev_atr * 13 + tr) / 14.
 *
 * Stop-and-reverse: close > upper band opens/holds LONG, close < lower band
 * opens/holds SHORT. The entry price and the ATR stop (entry -/+ ATR * mult)
 * are fixed on the bar where the position flips and carried until the next flip.
 *
 * This is the canonical implementation; scripts/donchian_atr_strategy.py
 * mirrors it and exists only as a cross-check.
 */
class DonchianAtrStrategy
{
    public int $dcUpperPeriod;
    public int $dcLowerPeriod;
    public int $atrPeriod;
    public float $atrMultiplier;

    public function __construct(
        int $dcUpperPeriod = 20,
        int $dcLowerPeriod = 10,
        int $atrPeriod = 14,
        float $atrMultiplier = 2.0
    ) {
        $this->dcUpperPeriod = $dcUpperPeriod;
        $this->dcLowerPeriod = $dcLowerPeriod;
        $this->atrPeriod = $atrPeriod;
        $this->atrMultiplier = $atrMultiplier;
    }

    /**
     * Enrich OHLC bars with bands, ATR, per-bar signal and position state.
     *
     * @param array $bars Chronological list of ['date' => string|Carbon,
     *                    'high' => float, 'low' => float, 'close' => float]
     * @return array Same bars plus dc_upper, dc_lower, atr, signal, position,
     *               entry_date, entry_price, stop_loss, stop_hit
     */
    public function compute(array $bars): array
    {
        $n = count($bars);
        $atr = $this->atr($bars);

        $position = 0;          // 1 = LONG, -1 = SHORT, 0 = FLAT (before first breakout)
        $entryDate = null;
        $entryPrice = null;
        $stopLoss = null;

        for ($i = 0; $i < $n; $i++) {
            $bar = &$bars[$i];
            $bar['dc_upper'] = $this->windowExtreme($bars, $i, $this->dcUpperPeriod, 'high', 'max');
            $bar['dc_lower'] = $this->windowExtreme($bars, $i, $this->dcLowerPeriod, 'low', 'min');
            $bar['atr'] = $atr[$i];

            $longBreak = $bar['dc_upper'] !== null && $bar['close'] > $bar['dc_upper'];
            $shortBreak = $bar['dc_lower'] !== null && $bar['close'] < $bar['dc_lower'];
            $bar['signal'] = $longBreak ? 'LONG' : ($shortBreak ? 'SHORT' : 'NEUTRAL');

            // A trend re-breaks its band bar after bar; only a direction
            // change is a new entry, fixing the entry price and the ATR stop
            $direction = $longBreak ? 1 : ($shortBreak ? -1 : $position);
            if ($direction !== $position && $direction !== 0) {
                $position = $direction;
                $entryDate = $bar['date'];
                $entryPrice = $bar['close'];
                $stopLoss = $atr[$i] !== null
                    ? $bar['close'] - $position * $atr[$i] * $this->atrMultiplier
                    : null;
            }

            $bar['position'] = $position === 1 ? 'LONG' : ($position === -1 ? 'SHORT' : 'FLAT');
            $bar['entry_date'] = $position !== 0 ? $entryDate : null;
            $bar['entry_price'] = $position !== 0 ? $entryPrice : null;
            $bar['stop_loss'] = $position !== 0 ? $stopLoss : null;
            $bar['stop_hit'] = $stopLoss !== null && (
                ($position === 1 && $bar['close'] < $stopLoss) ||
                ($position === -1 && $bar['close'] > $stopLoss)
            );
        }

        return $bars;
    }

    /**
     * Latest strategy state for one symbol — what signals:compute persists.
     */
    public function snapshot(array $bars): ?array
    {
        if (count($bars) <= $this->dcUpperPeriod) {
            return null; // not enough history for the 20-period band
        }

        $enriched = $this->compute($bars);

        return end($enriched);
    }

    /**
     * Rolling max/min over the $period bars strictly before index $i
     * (the shift-by-1 that prevents repainting). Null during warm-up.
     */
    private function windowExtreme(array $bars, int $i, int $period, string $field, string $fn): ?float
    {
        if ($i < $period) {
            return null;
        }

        $extreme = $bars[$i - $period][$field];
        for ($j = $i - $period + 1; $j < $i; $j++) {
            $extreme = $fn === 'max'
                ? max($extreme, $bars[$j][$field])
                : min($extreme, $bars[$j][$field]);
        }

        return $extreme;
    }

    /**
     * Classic Wilder ATR: true range (first bar: high - low), SMA seed over
     * the first $atrPeriod bars, then recursive smoothing. Null during warm-up.
     */
    private function atr(array $bars): array
    {
        $n = count($bars);
        $tr = [];
        for ($i = 0; $i < $n; $i++) {
            $hl = $bars[$i]['high'] - $bars[$i]['low'];
            if ($i === 0) {
                $tr[] = $hl;
                continue;
            }
            $prevClose = $bars[$i - 1]['close'];
            $tr[] = max($hl, abs($bars[$i]['high'] - $prevClose), abs($bars[$i]['low'] - $prevClose));
        }

        $atr = array_fill(0, $n, null);
        if ($n < $this->atrPeriod) {
            return $atr;
        }

        $atr[$this->atrPeriod - 1] = array_sum(array_slice($tr, 0, $this->atrPeriod)) / $this->atrPeriod;
        for ($i = $this->atrPeriod; $i < $n; $i++) {
            $atr[$i] = ($atr[$i - 1] * ($this->atrPeriod - 1) + $tr[$i]) / $this->atrPeriod;
        }

        return $atr;
    }
}
