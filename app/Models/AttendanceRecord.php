<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $table = 'attendance_records';

    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
        'late_minutes',
        'early_leave_minutes',
        'worked_hours',
        'status',
        'note',
    ];

    protected $casts = [
        'date'     => 'date',
        'clock_in' => 'datetime',
        'clock_out'=> 'datetime',
        'worked_hours' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Status label in Chinese
    public function statusLabel(): string
    {
        return match($this->status) {
            'normal'      => '正常',
            'late'        => '遲到',
            'early_leave' => '早退',
            'absent'      => '曠職',
            'on_leave'    => '請假',
            'holiday'     => '假日',
            default       => '未知',
        };
    }

    // Status badge color
    public function statusColor(): string
    {
        return match($this->status) {
            'normal'      => 'bg-success',
            'late'        => 'bg-warning text-dark',
            'early_leave' => 'bg-warning text-dark',
            'absent'      => 'bg-danger',
            'on_leave'    => 'bg-info text-dark',
            'holiday'     => 'bg-secondary',
            default       => 'bg-secondary',
        };
    }
}
