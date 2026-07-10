<?php

namespace App\Http\Controllers;

use App\Models\Stock;

class ScreenerController extends Controller
{
    public function index()
    {
        $symbols = Stock::select('symbol')->distinct()->orderBy('symbol')->pluck('symbol');

        $results = [];
        foreach ($symbols as $symbol) {
            $rows = Stock::where('symbol', $symbol)
                ->orderBy('date')
                ->get(['date', 'close', 'high', 'low', 'volume']);

            if ($rows->count() >= 2) {
                $results[] = $this->computeMetrics($symbol, $rows);
            }
        }

        return response()->json($results);
    }

    private function computeMetrics($symbol, $rows)
    {
        $first = $rows->first();
        $last = $rows->last();
        $lastClose = $last->close;
        $lastDate = $last->date;
        $spanDays = $first->date->diffInDays($lastDate);

        $lastYear = $rows->filter(function ($r) use ($lastDate) {
            return $r->date->gte($lastDate->copy()->subDays(365));
        })->values();

        $growth = $spanDays >= 90
            ? round((pow($lastClose / $first->close, 365 / $spanDays) - 1) * 100, 2)
            : null;

        $volatility = $this->annualizedVolatility($rows->pluck('close')->all());
        $maxDrawdown = $this->maxDrawdown($lastYear->pluck('close')->all());
        $high52w = $lastYear->max('high');

        $ema50d = $this->ema($rows->pluck('close')->all(), 50);
        $ema200d = $this->ema($rows->pluck('close')->all(), 200);
        $ema50w = $this->ema($this->weeklyCloses($rows), 50);

        $return3m = $this->returnOverDays($rows, 91);
        $return6m = $this->returnOverDays($rows, 182);

        $riskChecks = [
            '3M return positive' => $return3m !== null && $return3m > 0,
            '6M return positive' => $return6m !== null && $return6m > 0,
            'Price above 50-day EMA' => $ema50d !== null && $lastClose > $ema50d,
            'Price above 200-day EMA' => $ema200d !== null && $lastClose > $ema200d,
            'Volatility below 40%' => $volatility !== null && $volatility < 40,
            'Max drawdown above -30%' => $maxDrawdown !== null && $maxDrawdown > -30,
        ];

        return [
            'symbol' => $symbol,
            'last_close' => round($lastClose, 2),
            'last_date' => $lastDate->format('Y-m-d'),
            'return_1m' => $this->returnOverDays($rows, 30),
            'return_3m' => $return3m,
            'return_6m' => $return6m,
            'return_1y' => $this->returnOverDays($rows, 365),
            'growth' => $growth,
            'risk_score' => count(array_filter($riskChecks)),
            'risk_checks' => $riskChecks,
            'ema_trend' => $ema50w !== null ? ($lastClose > $ema50w ? 'UP' : 'DOWN') : null,
            'off_52w_high' => $high52w > 0 ? round(($lastClose / $high52w - 1) * 100, 2) : null,
            'volatility' => $volatility,
            'max_drawdown' => $maxDrawdown,
        ];
    }

    private function returnOverDays($rows, $days)
    {
        $last = $rows->last();
        $cutoff = $last->date->copy()->subDays($days);

        if ($rows->first()->date->gt($cutoff)) {
            return null;
        }

        $base = null;
        foreach ($rows as $row) {
            if ($row->date->lte($cutoff)) {
                $base = $row->close;
            } else {
                break;
            }
        }

        return $base > 0 ? round(($last->close / $base - 1) * 100, 2) : null;
    }

    private function annualizedVolatility(array $closes)
    {
        $returns = [];
        for ($i = 1; $i < count($closes); $i++) {
            if ($closes[$i - 1] > 0 && $closes[$i] > 0) {
                $returns[] = log($closes[$i] / $closes[$i - 1]);
            }
        }

        $n = count($returns);
        if ($n < 20) {
            return null;
        }

        $mean = array_sum($returns) / $n;
        $variance = array_sum(array_map(function ($r) use ($mean) {
            return ($r - $mean) ** 2;
        }, $returns)) / ($n - 1);

        return round(sqrt($variance) * sqrt(252) * 100, 2);
    }

    private function maxDrawdown(array $closes)
    {
        if (count($closes) < 2) {
            return null;
        }

        $peak = $closes[0];
        $maxDrawdown = 0;
        foreach ($closes as $close) {
            $peak = max($peak, $close);
            $maxDrawdown = min($maxDrawdown, $close / $peak - 1);
        }

        return round($maxDrawdown * 100, 2);
    }

    private function ema(array $values, int $period)
    {
        if (count($values) < $period) {
            return null;
        }

        $k = 2 / ($period + 1);
        $ema = array_sum(array_slice($values, 0, $period)) / $period;
        for ($i = $period; $i < count($values); $i++) {
            $ema = $values[$i] * $k + $ema * (1 - $k);
        }

        return $ema;
    }

    private function weeklyCloses($rows)
    {
        $weekly = [];
        foreach ($rows as $row) {
            $weekly[$row->date->format('o-W')] = $row->close;
        }

        return array_values($weekly);
    }
}
