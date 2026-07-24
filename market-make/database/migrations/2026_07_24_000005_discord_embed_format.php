<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // compute_signal_alert: builds a Discord embed payload and stores it in message
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION compute_signal_alert()
            RETURNS TRIGGER AS $$
            DECLARE
                emoji     TEXT;
                action    TEXT;
                direction TEXT;
                color     INT;
                payload   JSONB;
            BEGIN
                IF NEW.position IS DISTINCT FROM NEW.alerted_position THEN
                    IF NEW.position = 'LONG' THEN
                        emoji     := '🟢';
                        action    := 'BUY';
                        direction := 'Upper band breakout';
                        color     := 5763719;   -- green
                    ELSIF NEW.position = 'SHORT' THEN
                        emoji     := '🔴';
                        action    := 'SELL';
                        direction := 'Lower band breakout';
                        color     := 15548997;  -- red
                    ELSE
                        emoji     := '⚪';
                        action    := 'EXIT';
                        direction := 'Position closed';
                        color     := 9807270;   -- grey
                    END IF;

                    payload := jsonb_build_object(
                        'embeds', jsonb_build_array(
                            jsonb_build_object(
                                'color',       color,
                                'title',       emoji || ' ' || action || ' ' || NEW.symbol,
                                'description', direction || ' on ' || to_char(NEW.date, 'YYYY-MM-DD') || ' — Donchian(20/10) + ATR(14)',
                                'fields', jsonb_build_array(
                                    jsonb_build_object('name', 'Price',     'value', NEW.close::text,                        'inline', true),
                                    jsonb_build_object('name', 'Stop loss', 'value', COALESCE(NEW.stop_loss::text, '—'),     'inline', true),
                                    jsonb_build_object('name', 'ATR(14)',   'value', COALESCE(NEW.atr::text, '—'),           'inline', true)
                                )
                            )
                        )
                    );

                    INSERT INTO alerts (symbol, close, message)
                    VALUES (NEW.symbol, NEW.close, payload::text);

                    NEW.alerted_position := NEW.position;
                END IF;

                IF NEW.stop_hit = true AND (NEW.stop_alerted_for IS DISTINCT FROM NEW.entry_date) THEN
                    payload := jsonb_build_object(
                        'embeds', jsonb_build_array(
                            jsonb_build_object(
                                'color',       15105570,  -- orange
                                'title',       '🛑 STOP HIT ' || NEW.symbol,
                                'description', 'Stop loss triggered — Donchian(20/10) + ATR(14)',
                                'fields', jsonb_build_array(
                                    jsonb_build_object('name', 'Entry date',  'value', to_char(NEW.entry_date, 'YYYY-MM-DD'),         'inline', true),
                                    jsonb_build_object('name', 'Entry price', 'value', COALESCE(NEW.entry_price::text, '—'),          'inline', true),
                                    jsonb_build_object('name', 'Stop loss',   'value', COALESCE(NEW.stop_loss::text, '—'),            'inline', true)
                                )
                            )
                        )
                    );

                    INSERT INTO alerts (symbol, close, message)
                    VALUES (NEW.symbol, NEW.close, payload::text);

                    NEW.stop_alerted_for := NEW.entry_date;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // notify_discord: sends the pre-built embed payload stored in message
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION notify_discord()
            RETURNS TRIGGER AS $$
            DECLARE
                webhook_url TEXT;
            BEGIN
                SELECT decrypted_secret INTO webhook_url
                FROM vault.decrypted_secrets
                WHERE name = 'discord_webhook_url'
                LIMIT 1;

                PERFORM net.http_post(
                    url     := webhook_url,
                    body    := NEW.message::jsonb,
                    headers := '{"Content-Type": "application/json"}'::jsonb
                );
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION compute_signal_alert()
            RETURNS TRIGGER AS $$
            DECLARE
                emoji     TEXT;
                action    TEXT;
                direction TEXT;
                msg       TEXT;
            BEGIN
                IF NEW.position IS DISTINCT FROM NEW.alerted_position THEN
                    IF NEW.position = 'LONG' THEN
                        emoji := '🟢'; action := 'BUY'; direction := 'Upper band breakout';
                    ELSIF NEW.position = 'SHORT' THEN
                        emoji := '🔴'; action := 'SELL'; direction := 'Lower band breakout';
                    ELSE
                        emoji := '⚪'; action := 'EXIT'; direction := 'Position closed';
                    END IF;

                    msg := emoji || ' **' || action || ' ' || NEW.symbol || '**' || chr(10) ||
                           direction || ' on ' || to_char(NEW.date, 'YYYY-MM-DD') || ' — Donchian(20/10) + ATR(14)' || chr(10) ||
                           '**Price** · **Stop loss** · **ATR(14)**' || chr(10) ||
                           COALESCE(NEW.close::text, '—') || ' · ' ||
                           COALESCE(NEW.stop_loss::text, '—') || ' · ' ||
                           COALESCE(NEW.atr::text, '—');

                    INSERT INTO alerts (symbol, close, message) VALUES (NEW.symbol, NEW.close, msg);
                    NEW.alerted_position := NEW.position;
                END IF;

                IF NEW.stop_hit = true AND (NEW.stop_alerted_for IS DISTINCT FROM NEW.entry_date) THEN
                    msg := '🛑 **STOP HIT ' || NEW.symbol || '**' || chr(10) ||
                           'Entry: ' || to_char(NEW.entry_date, 'YYYY-MM-DD') ||
                           ' @ ' || COALESCE(NEW.entry_price::text, '—') || chr(10) ||
                           'Stop loss: ' || COALESCE(NEW.stop_loss::text, '—');

                    INSERT INTO alerts (symbol, close, message) VALUES (NEW.symbol, NEW.close, msg);
                    NEW.stop_alerted_for := NEW.entry_date;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION notify_discord()
            RETURNS TRIGGER AS $$
            DECLARE
                webhook_url TEXT;
            BEGIN
                SELECT decrypted_secret INTO webhook_url
                FROM vault.decrypted_secrets
                WHERE name = 'discord_webhook_url'
                LIMIT 1;

                PERFORM net.http_post(
                    url     := webhook_url,
                    body    := json_build_object('content', NEW.message)::jsonb,
                    headers := '{"Content-Type": "application/json"}'::jsonb
                );
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }
};
