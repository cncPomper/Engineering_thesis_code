-- Migration: create_alerts_table
-- Run in: Supabase SQL editor

CREATE TABLE IF NOT EXISTS alerts (
    id           BIGSERIAL PRIMARY KEY,
    symbol       TEXT NOT NULL,
    close        NUMERIC(10, 4) NOT NULL,
    triggered_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    message      TEXT
);

-- Trigger function: POST to Discord webhook on new alert
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

CREATE TRIGGER on_alert_insert
    AFTER INSERT ON alerts
    FOR EACH ROW
    EXECUTE FUNCTION notify_discord();

-- Computing trigger: watches signals table and generates alerts automatically
CREATE OR REPLACE FUNCTION compute_signal_alert()
RETURNS TRIGGER AS $$
BEGIN
    -- Position changed vs last alerted position
    IF NEW.position IS DISTINCT FROM NEW.alerted_position THEN
        INSERT INTO alerts (symbol, close, message)
        VALUES (
            NEW.symbol,
            NEW.close,
            'Position changed to ' || NEW.position || ' for ' || NEW.symbol
        );
        NEW.alerted_position := NEW.position;
    END IF;

    -- Stop hit and not yet alerted for this entry
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

-- BEFORE UPDATE so NEW can be modified (updates alerted_position / stop_alerted_for in-place)
CREATE TRIGGER on_signal_update
    BEFORE UPDATE ON signals
    FOR EACH ROW
    EXECUTE FUNCTION compute_signal_alert();
