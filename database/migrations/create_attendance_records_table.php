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
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                  ->constrained('employees')
                  ->onDelete('cascade');

            $table->date('date');

            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();

            $table->decimal('worked_hours',4,2)->default(0);

            $table->integer('late_minutes')->default(0);
            $table->integer('early_leave_minutes')->default(0);

            $table->string('status')->default('normal');

            $table->timestamps();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->decimal('worked_hours', 5, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
