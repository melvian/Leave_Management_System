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
        // Delete duplicate records first keeping only the first one per employee per date
        $duplicates = DB::select("
            SELECT id FROM attendance_records
            WHERE id NOT IN (
                SELECT MIN(id) FROM attendance_records GROUP BY employee_id, date
            )
        ");
        foreach ($duplicates as $dup) {
            DB::delete("DELETE FROM attendance_records WHERE id = ?", [$dup->id]);
        }

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unique(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'date']);
        });
    }
};
