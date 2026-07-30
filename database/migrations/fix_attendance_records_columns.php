<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add worked_hours if missing
        if (!Schema::hasColumn('attendance_records', 'worked_hours')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->decimal('worked_hours', 5, 2)->nullable()->after('clock_out');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('worked_hours');
        });
    }
};
