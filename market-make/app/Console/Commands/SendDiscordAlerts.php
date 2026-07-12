<?php

namespace App\Console\Commands;

use App\Models\Signal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SendDiscordAlerts extends Command
{
    protected $signature = 'alerts:discord
        {--test : Send a connectivity test message and exit}
        {--baseline-silent=1 : On the very first run per symbol, record state without alerting (1) or alert immediately (0)}';

    protected $description = 'Send BUY/SELL/stop-hit alerts to Discord for signal state changes since the last run';

    private const COLOR_GREEN = 0x26a69a;
    private const COLOR_RED = 0xef5350;
    private const COLOR_ORANGE = 0xff9800;

    public function handle()
    {
        $webhook = config('services.discord.webhook');

        if (!$webhook) {
            $this->error('DISCORD_WEBHOOK_URL is not set in .env');
            return 1;
        }

        if ($this->option('test')) {
            $ok = $this->post($webhook, [
                'content' => '✅ **market-make** connected — Donchian(20/10) + ATR(14) alerts will appear here.',
            ]);
            $this->info($ok ? 'Test message sent!' : 'Test message FAILED (see error above)');
            return $ok ? 0 : 1;
        }

        $sent = 0;

        foreach (Signal::orderBy('symbol')->get() as $signal) {
            // First time we see a symbol: record the current position as the
            // baseline without alerting, so onboarding 100 tickers does not
            // flood the channel with positions that opened weeks ago
            if ($signal->alerted_position === null && $this->option('baseline-silent') !== '0') {
                $signal->update(['alerted_position' => $signal->position]);
                $this->line("– {$signal->symbol}: baseline recorded ({$signal->position}), no alert");
                continue;
            }

            if ($signal->position !== $signal->alerted_position && $signal->position !== 'FLAT') {
                if ($this->sendPositionAlert($webhook, $signal)) {
                    $signal->update(['alerted_position' => $signal->position]);
                    $sent++;
                }
            }

            $stopAlreadyAlerted = $signal->stop_alerted_for
                && $signal->entry_date
                && $signal->stop_alerted_for->equalTo($signal->entry_date);

            if ($signal->stop_hit && !$stopAlreadyAlerted) {
                if ($this->sendStopAlert($webhook, $signal)) {
                    $signal->update(['stop_alerted_for' => $signal->entry_date]);
                    $sent++;
                }
            }
        }

        $this->info("Discord alerts completed ($sent sent).");
    }

    private function sendPositionAlert($webhook, Signal $signal): bool
    {
        $isLong = $signal->position === 'LONG';
        $action = $isLong ? 'BUY' : 'SELL';
        $emoji = $isLong ? '🟢' : '🔴';

        return $this->post($webhook, [
            'embeds' => [[
                'title' => "$emoji $action {$signal->symbol}",
                'description' => sprintf(
                    '%s breakout on %s — Donchian(20/10) + ATR(14)',
                    $isLong ? 'Upper band' : 'Lower band',
                    $signal->date->format('Y-m-d')
                ),
                'color' => $isLong ? self::COLOR_GREEN : self::COLOR_RED,
                'fields' => [
                    ['name' => 'Price', 'value' => number_format($signal->close, 2), 'inline' => true],
                    ['name' => 'Stop loss', 'value' => $signal->stop_loss !== null ? number_format($signal->stop_loss, 2) : '—', 'inline' => true],
                    ['name' => 'ATR(14)', 'value' => $signal->atr !== null ? number_format($signal->atr, 2) : '—', 'inline' => true],
                ],
            ]],
        ]);
    }

    private function sendStopAlert($webhook, Signal $signal): bool
    {
        return $this->post($webhook, [
            'embeds' => [[
                'title' => "⚠️ STOP HIT {$signal->symbol}",
                'description' => sprintf(
                    '%s position from %s (entry %s) closed through its stop on %s — consider exiting.',
                    $signal->position,
                    $signal->entry_date ? $signal->entry_date->format('Y-m-d') : '?',
                    number_format($signal->entry_price, 2),
                    $signal->date->format('Y-m-d')
                ),
                'color' => self::COLOR_ORANGE,
                'fields' => [
                    ['name' => 'Close', 'value' => number_format($signal->close, 2), 'inline' => true],
                    ['name' => 'Stop was', 'value' => number_format($signal->stop_loss, 2), 'inline' => true],
                ],
            ]],
        ]);
    }

    private function post($webhook, array $payload): bool
    {
        try {
            $response = Http::post($webhook, $payload);
        } catch (\Exception $e) {
            $this->error('Discord request failed: ' . $e->getMessage());
            return false;
        }

        // Stay under Discord's webhook rate limit (~30 requests/minute)
        usleep(400000);

        if (!$response->successful()) {
            $this->error("Discord returned HTTP {$response->status()}: " . $response->body());
            return false;
        }

        return true;
    }
}
