<?php

namespace App\Models;

use App\Enums\OvertimeStatus;
use Illuminate\Database\Eloquent\Model;

class OvertimeRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'overtime_reason',
        'date',
        'start_time',
        'end_time',
        'hours',
        'status',   
        'admin_note',
    ];

    protected $casts = [
        'date'=> 'date',
        'status'=> OvertimeStatus::class,
        'hours' => 'float',
    ];

    protected $table = 'overtime_records';

    public function employee(){
        return $this->belongsTo(Employee::class);
    }
}
