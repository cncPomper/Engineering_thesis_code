<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Computing trigger: watches signals, inserts into alerts on position change or stop hit
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION compute_signal_alert()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.position IS DISTINCT FROM NEW.alerted_position THEN
                    INSERT INTO alerts (symbol, close, message)
                    VALUES (
                        NEW.symbol,
                        NEW.close,
                        'Position changed to ' || NEW.position || ' for ' || NEW.symbol
                    );
                    NEW.alerted_position := NEW.position;
                END IF;

                IF NEW.stop_hit = true AND (NEW.stop_alerted_for IS DISTINCT FROM NEW.entry_date) THEN
                    INSERT INTO alerts (symbol, close, message)
                    VALUES (
                        NEW.symbol,
                        NEW.close,
                        'Stop hit for ' || NEW.symbol || ' (entry: ' || NEW.entry_date || ')'
                    );
                    NEW.stop_alerted_for := NEW.entry_date;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE TRIGGER on_signal_update
                BEFORE UPDATE ON signals
                FOR EACH ROW
                EXECUTE FUNCTION compute_signal_alert();
        SQL);

        // Notification trigger: POSTs to Discord when a row is inserted into alerts
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
                    body    := json_build_object(
                                 'content', 'New alert: ' || NEW.symbol || ' @ ' || NEW.close
                               )::jsonb,
                    headers := '{"Content-Type": "application/json"}'::jsonb
                );
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE TRIGGER on_alert_insert
                AFTER INSERT ON alerts
                FOR EACH ROW
                EXECUTE FUNCTION notify_discord();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS on_signal_update ON signals');
        DB::unprepared('DROP FUNCTION IF EXISTS compute_signal_alert');
        DB::unprepared('DROP TRIGGER IF EXISTS on_alert_insert ON alerts');
        DB::unprepared('DROP FUNCTION IF EXISTS notify_discord');
    }
};
