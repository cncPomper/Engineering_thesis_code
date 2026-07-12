<?php

namespace App\Console\Commands;

use App\Models\Signal;
use App\Models\Stock;
use App\Services\DonchianAtrStrategy;
use Illuminate\Console\Command;

class ComputeSignals extends Command
{
    protected $signature = 'signals:compute
        {--symbols= : Comma-separated list; defaults to every symbol in the stocks table}
        {--atr-multiplier=2.0 : Stop distance in ATRs}';

    protected $description = 'Run the Donchian(20/10) + ATR(14) strategy over DB price history and store the state per symbol';

    public function handle()
    {
        $symbols = $this->option('symbols')
            ? array_map('trim', explode(',', $this->option('symbols')))
            : Stock::select('symbol')->distinct()->orderBy('symbol')->pluck('symbol')->all();

        $strategy = new DonchianAtrStrategy(20, 10, 14, (float) $this->option('atr-multiplier'));

        foreach ($symbols as $symbol) {
            $bars = Stock::where('symbol', $symbol)
                ->orderBy('date')
                ->get(['date', 'high', 'low', 'close'])
                ->map(fn ($row) => [
                    'date' => $row->date->format('Y-m-d'),
                    'high' => $row->high,
                    'low' => $row->low,
                    'close' => $row->close,
                ])
                ->all();

            $state = $strategy->snapshot($bars);

            if ($state === null) {
                $this->warn("– $symbol: not enough history (" . count($bars) . " bars), skipped");
                continue;
            }

            // Alert bookkeeping columns are deliberately not touched here,
            // so alerts:discord can diff current state against what was sent
            Signal::updateOrCreate(
                ['symbol' => $symbol],
                [
                    'date' => $state['date'],
                    'close' => $state['close'],
                    'dc_upper' => $state['dc_upper'],
                    'dc_lower' => $state['dc_lower'],
                    'atr' => $state['atr'],
                    'signal' => $state['signal'],
                    'position' => $state['position'],
                    'entry_date' => $state['entry_date'],
                    'entry_price' => $state['entry_price'],
                    'stop_loss' => $state['stop_loss'],
                    'stop_hit' => $state['stop_hit'],
                ]
            );

            $stop = $state['stop_loss'] !== null ? number_format($state['stop_loss'], 2) : '—';
            $this->info(sprintf(
                '✓ %s [%s]: %s @ %.2f, stop %s%s',
                $symbol,
                $state['date'],
                $state['position'],
                $state['close'],
                $stop,
                $state['stop_hit'] ? ' ⚠ STOP HIT' : ''
            ));
        }

        $this->info('Signal computation completed!');
    }
}
