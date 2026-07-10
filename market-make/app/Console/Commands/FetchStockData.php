<?php

namespace App\Console\Commands;

use App\Models\Stock;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class FetchStockData extends Command
{
    protected $signature = 'stocks:fetch {--symbols=CDR,PKN,MBK,PLY,KGH,TPE} {--start=} {--end=}';
    protected $description = 'Fetch stock data from yfinance for specified symbols';

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
                $this->info("✓ $symbol is already up to date (latest record: " . Carbon::parse($symbolStart)->subDay()->format('Y-m-d') . ")");
                continue;
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

        $data = json_decode($process->getOutput(), true);

        if (!is_array($data) || empty($data)) {
            $this->warn("No data found for $symbol");
            return;
        }

        foreach ($data as $row) {
            Stock::updateOrCreate(
                ['symbol' => $symbol, 'date' => $row['date']],
                [
                    'open' => $row['open'],
                    'high' => $row['high'],
                    'low' => $row['low'],
                    'close' => $row['close'],
                    'volume' => $row['volume'],
                ]
            );
        }

        $this->info("✓ Imported " . count($data) . " records for $symbol");
    }
}
