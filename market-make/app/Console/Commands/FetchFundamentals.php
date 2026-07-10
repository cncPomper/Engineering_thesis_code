<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Stock;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class FetchFundamentals extends Command
{
    protected $signature = 'stocks:fundamentals {--symbols= : Comma-separated tickers; defaults to every symbol in the database}';
    protected $description = 'Fetch 5Y fundamentals from yfinance and compute the GROWTH reliability (R) score';

    public function handle()
    {
        $symbols = $this->option('symbols')
            ? array_map('trim', explode(',', $this->option('symbols')))
            : Stock::select('symbol')->distinct()->orderBy('symbol')->pluck('symbol')->all();

        if (empty($symbols)) {
            $this->warn('No symbols found. Fetch some price data first: php artisan stocks:fetch');
            return;
        }

        foreach ($symbols as $symbol) {
            $this->fetchSymbolFundamentals($symbol);
        }

        $this->info('Fundamentals fetch completed!');
    }

    private function fetchSymbolFundamentals($symbol)
    {
        $pythonScript = base_path('scripts/fetch_fundamentals.py');

        // Use Python from virtual environment if it exists
        $pythonPath = base_path('.venv/Scripts/python.exe');
        if (!file_exists($pythonPath)) {
            $pythonPath = base_path('.venv/bin/python');
        }
        if (!file_exists($pythonPath)) {
            $pythonPath = 'python';
        }

        $process = new Process([$pythonPath, $pythonScript, $symbol]);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error("Failed to fetch fundamentals for $symbol");
            $this->error($process->getErrorOutput());
            return;
        }

        $payload = json_decode($process->getOutput(), true);

        if (!is_array($payload)) {
            $this->warn("No fundamentals found for $symbol");
            return;
        }

        $reliability = $payload['reliability'] ?? null;

        Company::updateOrCreate(
            ['symbol' => $symbol],
            [
                'revenue_growth' => $payload['revenue_growth'] ?? null,
                'eps_growth' => $payload['eps_growth'] ?? null,
                'reliability_score' => $reliability['score'] ?? null,
                'reliability_max' => $reliability['max_score'] ?? null,
                'reliability_checks' => $reliability['checks'] ?? null,
            ]
        );

        $g = $payload['revenue_growth'] !== null ? $payload['revenue_growth'] . '%' : 'n/a';
        $r = $reliability !== null ? $reliability['label'] : 'n/a';
        $years = implode(', ', $payload['years_used'] ?? []);
        $this->info("✓ $symbol: G (revenue growth) = $g, R = $r" . ($years ? " [fiscal years: $years]" : ''));
    }
}
