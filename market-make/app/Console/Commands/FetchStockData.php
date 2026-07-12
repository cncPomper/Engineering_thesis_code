<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Stock;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class FetchStockData extends Command
{
    protected $signature = 'stocks:fetch {--symbols=CDR.WA,PKN.WA,MBK.WA,PLY.WA,KGH.WA,TPE.WA} {--start=} {--end=} {--force : Overwrite records that already exist in the database}';
    protected $description = 'Fetch stock data from yfinance for the given tickers (any exchange, e.g. NVDA, CDR.WA, BMW.DE)';

    public function handle()
    {
        $symbols = explode(',', $this->option('symbols'));
        $start = $this->option('start');
        $end = $this->option('end') ?? Carbon::now()->format('Y-m-d');

        $this->info("Fetching stock data for: " . implode(', ', $symbols));

        foreach ($symbols as $symbol) {
            $symbol = trim($symbol);
            $symbolStart = $start ?? $this->resolveStartDate($symbol);

            if ($symbolStart > $end) {
                if (Company::where('symbol', $symbol)->exists()) {
                    $this->info("✓ $symbol is already up to date (latest record: " . Carbon::parse($symbolStart)->subDay()->format('Y-m-d') . ")");
                    continue;
                }

                // Prices are current but company info is missing — fetch a single
                // day anyway to pick up the metadata (existing rows are skipped)
                $symbolStart = $end;
            }

            $this->info("Period for $symbol: $symbolStart to $end");
            $this->fetchSymbolData($symbol, $symbolStart, $end);
        }

        $this->info('Stock data fetch completed!');
    }

    private function resolveStartDate($symbol)
    {
        $latestDate = Stock::where('symbol', $symbol)->max('date');

        if ($latestDate) {
            return Carbon::parse($latestDate)->addDay()->format('Y-m-d');
        }

        return Carbon::now()->subMonths(3)->format('Y-m-d');
    }

    private function fetchSymbolData($symbol, $start, $end)
    {
        $pythonScript = base_path('scripts/fetch_stock_data.py');

        // Use Python from virtual environment if it exists
        $pythonPath = base_path('.venv/Scripts/python.exe');
        if (!file_exists($pythonPath)) {
            $pythonPath = base_path('.venv/bin/python');
        }
        if (!file_exists($pythonPath)) {
            $pythonPath = 'python';
        }

        $process = new Process([
            $pythonPath,
            $pythonScript,
            $symbol,
            $start,
            $end,
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            $this->error("Failed to fetch data for $symbol");
            $this->error($process->getErrorOutput());
            return;
        }

        $payload = json_decode($process->getOutput(), true);
        $data = is_array($payload) ? ($payload['data'] ?? []) : [];
        $info = is_array($payload) ? ($payload['info'] ?? null) : null;

        if (!empty($info['name'])) {
            Company::updateOrCreate(
                ['symbol' => $symbol],
                [
                    'name' => $info['name'],
                    'sector' => $info['sector'] ?? null,
                    'industry' => $info['industry'] ?? null,
                ]
            );
            $this->info("ℹ $symbol: " . $info['name'] . ($info['sector'] ? " ({$info['sector']})" : ''));
        }

        if (empty($data)) {
            $this->warn("No data found for $symbol");
            return;
        }

        $existingDates = Stock::where('symbol', $symbol)
            ->pluck('date')
            ->map(function ($date) {
                return Carbon::parse($date)->format('Y-m-d');
            })
            ->flip();

        $force = $this->option('force');
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($data as $row) {
            $exists = isset($existingDates[$row['date']]);

            if ($exists && !$force) {
                $skipped++;
                continue;
            }

            // Pass a Carbon instance so the lookup matches the stored datetime
            // format ('Y-m-d H:i:s'); a plain 'Y-m-d' string never matches and
            // updateOrCreate would try to insert a duplicate
            Stock::updateOrCreate(
                ['symbol' => $symbol, 'date' => Carbon::parse($row['date'])],
                [
                    'open' => $row['open'],
                    'high' => $row['high'],
                    'low' => $row['low'],
                    'close' => $row['close'],
                    'volume' => $row['volume'],
                ]
            );

            $exists ? $updated++ : $created++;
        }

        $this->updateDataRange($symbol);

        $summary = "✓ $symbol: $created new";
        if ($updated > 0) {
            $summary .= ", $updated refreshed";
        }
        if ($skipped > 0) {
            $summary .= ", $skipped already in database (use --force to refresh)";
        }
        $this->info($summary);
    }

    private function updateDataRange($symbol)
    {
        $range = Stock::where('symbol', $symbol)
            ->selectRaw('MIN(date) as data_from, MAX(date) as data_to')
            ->first();

        if ($range && $range->data_from) {
            Company::updateOrCreate(
                ['symbol' => $symbol],
                [
                    'data_from' => Carbon::parse($range->data_from),
                    'data_to' => Carbon::parse($range->data_to),
                ]
            );
        }
    }
}
