<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->double('revenue_growth')->nullable();     // G: annualized 5Y revenue growth, %
            $table->double('eps_growth')->nullable();         // annualized 5Y diluted EPS growth, %
            $table->integer('reliability_score')->nullable(); // R: passed checks
            $table->integer('reliability_max')->nullable();   // R: total checks (6)
            $table->text('reliability_checks')->nullable();   // R: per-check details (JSON)
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'revenue_growth',
                'eps_growth',
                'reliability_score',
                'reliability_max',
                'reliability_checks',
            ]);
        });
    }
};
