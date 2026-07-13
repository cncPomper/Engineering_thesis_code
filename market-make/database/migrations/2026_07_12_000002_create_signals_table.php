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
            $table->double('close');
            $table->double('dc_upper')->nullable();
            $table->double('dc_lower')->nullable();
            $table->double('atr')->nullable();
            $table->string('signal');           // latest bar: LONG / SHORT / NEUTRAL
            $table->string('position');         // carried state: LONG / SHORT / FLAT
            $table->date('entry_date')->nullable();
            $table->double('entry_price')->nullable();
            $table->double('stop_loss')->nullable();
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
