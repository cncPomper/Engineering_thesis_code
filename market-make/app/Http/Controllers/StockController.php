<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StockController extends Controller
{
    public function range(Request $request)
    {
        $request->validate([
            'start' => 'required|date_format:d.m.Y',
            'end' => 'required|date_format:d.m.Y',
            'timeframe' => 'in:1D,1W,1M',
            'symbol' => 'in:CDR,PKN,MBK,PLY,KGH,TPE',
        ]);

        $start = Carbon::createFromFormat('d.m.Y', $request->start)->startOfDay();
        $end = Carbon::createFromFormat('d.m.Y', $request->end)->endOfDay();
        $timeframe = $request->get('timeframe', '1D');
        $symbol = $request->get('symbol', 'MOC');

        $data = Stock::where('symbol', $symbol)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();

        if ($data->isEmpty()) {
            return response()->json(['message' => 'No data found', 'data' => []]);
        }

        if ($timeframe === '1D') {
            return response()->json([
                'timeframe' => '1D',
                'symbol' => $symbol,
                'start' => $start->format('d.m.Y'),
                'end' => $end->format('d.m.Y'),
                'data' => $this->formatDailyData($data),
                'grid' => $this->createGridData($data),
            ]);
        }

        $aggregated = $this->aggregateData($data, $timeframe);

        return response()->json([
            'timeframe' => $timeframe,
            'symbol' => $symbol,
            'start' => $start->format('d.m.Y'),
            'end' => $end->format('d.m.Y'),
            'data' => $aggregated,
            'grid' => $this->createGridData($data),
        ]);
    }

    private function formatDailyData($data)
    {
        return $data->map(function ($stock) {
            return [
                'date' => $stock->date->format('d.m.Y'),
                'open' => $stock->open,
                'high' => $stock->high,
                'low' => $stock->low,
                'close' => $stock->close,
                'volume' => $stock->volume,
            ];
        })->toArray();
    }

    private function aggregateData($data, $timeframe)
    {
        if ($timeframe === '1W') {
            return $this->aggregateByFiveDays($data);
        } elseif ($timeframe === '1M') {
            return $this->aggregateByMonth($data);
        }

        return $this->formatDailyData($data);
    }

    private function aggregateByFiveDays($data)
    {
        $grouped = [];

        foreach ($data as $stock) {
            $date = Carbon::parse($stock->date);
            $dayOfWeek = $date->dayOfWeek; // 0=Sunday, 1=Monday, ..., 5=Friday, 6=Saturday

            // Find Monday of current week
            $mondayDate = $date->copy()->startOfWeek(Carbon::MONDAY);
            $weekKey = $mondayDate->format('Y-m-d');

            if (!isset($grouped[$weekKey])) {
                $grouped[$weekKey] = [
                    'period' => $mondayDate->format('W/Y'),
                    'start_date' => $stock->date,
                    'open' => $stock->open,
                    'high' => $stock->high,
                    'low' => $stock->low,
                    'close' => $stock->close,
                    'volume' => $stock->volume,
                    'days' => 1,
                ];
            } else {
                $grouped[$weekKey]['high'] = max($grouped[$weekKey]['high'], $stock->high);
                $grouped[$weekKey]['low'] = min($grouped[$weekKey]['low'], $stock->low);
                $grouped[$weekKey]['close'] = $stock->close;
                $grouped[$weekKey]['volume'] += $stock->volume;
                $grouped[$weekKey]['days'] += 1;
                $grouped[$weekKey]['end_date'] = $stock->date;
            }
        }

        return array_map(function ($week) {
            return [
                'period' => $week['period'] . ' (Mon-Fri)',
                'start_date' => Carbon::parse($week['start_date'])->format('d.m.Y'),
                'end_date' => isset($week['end_date']) ? Carbon::parse($week['end_date'])->format('d.m.Y') : Carbon::parse($week['start_date'])->format('d.m.Y'),
                'open' => $week['open'],
                'high' => $week['high'],
                'low' => $week['low'],
                'close' => $week['close'],
                'volume' => $week['volume'],
                'days' => $week['days'],
            ];
        }, array_values($grouped));
    }

    private function aggregateByMonth($data)
    {
        $grouped = [];

        foreach ($data as $stock) {
            $monthKey = Carbon::parse($stock->date)->format('Y-m');

            if (!isset($grouped[$monthKey])) {
                $grouped[$monthKey] = [
                    'period' => $monthKey,
                    'start_date' => $stock->date,
                    'open' => $stock->open,
                    'high' => $stock->high,
                    'low' => $stock->low,
                    'close' => $stock->close,
                    'volume' => $stock->volume,
                    'days' => 1,
                ];
            } else {
                $grouped[$monthKey]['high'] = max($grouped[$monthKey]['high'], $stock->high);
                $grouped[$monthKey]['low'] = min($grouped[$monthKey]['low'], $stock->low);
                $grouped[$monthKey]['close'] = $stock->close;
                $grouped[$monthKey]['volume'] += $stock->volume;
                $grouped[$monthKey]['days'] += 1;
                $grouped[$monthKey]['end_date'] = $stock->date;
            }
        }

        return array_map(function ($month) {
            return [
                'period' => $month['period'],
                'start_date' => Carbon::parse($month['start_date'])->format('d.m.Y'),
                'end_date' => isset($month['end_date']) ? Carbon::parse($month['end_date'])->format('d.m.Y') : Carbon::parse($month['start_date'])->format('d.m.Y'),
                'open' => $month['open'],
                'high' => $month['high'],
                'low' => $month['low'],
                'close' => $month['close'],
                'volume' => $month['volume'],
                'days' => $month['days'],
            ];
        }, array_values($grouped));
    }

    private function createGridData($data)
    {
        $gridSize = 10;
        $chunks = $data->chunk($gridSize);

        return $chunks->map(function ($chunk) {
            $first = $chunk->first();
            $last = $chunk->last();

            return [
                'start_date' => $first->date->format('d.m.Y'),
                'end_date' => $last->date->format('d.m.Y'),
                'open' => $first->open,
                'high' => $chunk->max('high'),
                'low' => $chunk->min('low'),
                'close' => $last->close,
                'volume' => $chunk->sum('volume'),
                'count' => $chunk->count(),
                'data' => $chunk->map(function ($stock) {
                    return [
                        'date' => $stock->date->format('d.m.Y'),
                        'open' => $stock->open,
                        'high' => $stock->high,
                        'low' => $stock->low,
                        'close' => $stock->close,
                        'volume' => $stock->volume,
                    ];
                })->toArray(),
            ];
        })->values()->toArray();
    }
}
