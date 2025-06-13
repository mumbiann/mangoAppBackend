<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('farmer_id')->constrained()->onDelete('cascade');
            $table->float('size');
            $table->string('district');
            $table->string('village');
            $table->date('planting_date');
            $table->unsignedTinyInteger('current_season_month')->default(1);
            $table->timestamps();

            $table->index('current_season_month', 'farms_current_season_month_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
