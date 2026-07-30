<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftSetting extends Model
{
    protected $fillable = [
        'department',
        'shift_start',
        'shift_end',
        'late_tolerance',
    ];

    // Get shift for a department, fallback to default if not set
    public static function forDepartment(string $department): object
    {
        return static::where('department', $department)->first()
            ?? (object)[
                'shift_start'    => '09:00',
                'shift_end'      => '18:00',
                'late_tolerance' => 11,
            ];
    }
}
