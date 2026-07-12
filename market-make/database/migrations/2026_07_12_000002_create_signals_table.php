<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique(); // one row = current strategy state per symbol
            $table->date('date');               // latest bar the state was computed from
            $table->float('close', 12, 4);
            $table->float('dc_upper', 12, 4)->nullable();
            $table->float('dc_lower', 12, 4)->nullable();
            $table->float('atr', 12, 4)->nullable();
            $table->string('signal');           // latest bar: LONG / SHORT / NEUTRAL
            $table->string('position');         // carried state: LONG / SHORT / FLAT
            $table->date('entry_date')->nullable();
            $table->float('entry_price', 12, 4)->nullable();
            $table->float('stop_loss', 12, 4)->nullable();
            $table->boolean('stop_hit')->default(false);

            // Alert bookkeeping (kept across recomputes so alerts stay idempotent)
            $table->string('alerted_position')->nullable(); // last position Discord was told about
            $table->date('stop_alerted_for')->nullable();   // entry_date whose stop-hit was alerted
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signals');
    }
};
