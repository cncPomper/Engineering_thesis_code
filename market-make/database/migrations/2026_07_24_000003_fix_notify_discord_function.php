<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS notify_discord CASCADE');
    }
};
