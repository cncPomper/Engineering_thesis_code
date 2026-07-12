<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->date('data_from')->nullable(); // earliest stocks.date for this symbol
            $table->date('data_to')->nullable();   // latest stocks.date for this symbol
        });

        // Backfill from the price history already in the database
        DB::statement('
            UPDATE companies SET
                data_from = (SELECT MIN(date) FROM stocks WHERE stocks.symbol = companies.symbol),
                data_to = (SELECT MAX(date) FROM stocks WHERE stocks.symbol = companies.symbol)
        ');
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['data_from', 'data_to']);
        });
    }
};
