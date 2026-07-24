<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('symbol'); // MOC, AMB, etc.
            $table->date('date');
            $table->double('open');
            $table->double('high');
            $table->double('low');
            $table->double('close');
            $table->bigInteger('volume');
            $table->timestamps();

            $table->unique(['symbol', 'date']);
            $table->index(['symbol', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
