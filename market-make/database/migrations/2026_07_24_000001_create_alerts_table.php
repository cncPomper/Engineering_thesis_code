<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('alerts')) {
            return;
        }

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->decimal('close', 10, 4);
            $table->timestampTz('triggered_at')->default(DB::raw('NOW()'));
            $table->text('message')->nullable();
        });

        DB::unprepared('ALTER TABLE alerts ENABLE ROW LEVEL SECURITY');
        DB::unprepared('CREATE POLICY "service role only" ON alerts FOR ALL USING (false)');
    }

    public function down(): void
    {
        DB::unprepared('DROP POLICY IF EXISTS "service role only" ON alerts');
        Schema::dropIfExists('alerts');
    }
};
