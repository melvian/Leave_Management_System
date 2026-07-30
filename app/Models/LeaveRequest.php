<?php

namespace App\Models;

use App\Enums\LeaveType;
use App\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_type',
        'leave_reason',
        'start_date',
        'end_date',
        'days',
        'hours',
        'start_time',
        'end_time',        
        'status',
        'current_approver',
        'admin_note',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'leave_type'   => LeaveType::class,
        'status'       => LeaveStatus::class,
        'hours'        => 'float',
        'days'         => 'float',
    ];

    protected $table = 'leave_requests';

    public function employee(){
        return $this->belongsTo(Employee::class);
    }

    public function setApprovalRouting():void{
        $this->current_approver = 'manager';
    }
}
