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
        Schema::create('shift_settings', function (Blueprint $table) {
            $table->id();
            $table->string('department')->unique();
            $table->string('shift_start')->default('09:00');
            $table->string('shift_end')->default('18:00');
            $table->integer('late_tolerance')->default(10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_settings');
    }
};
