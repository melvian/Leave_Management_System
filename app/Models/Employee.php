<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\LeaveRequest;
use App\Models\OvertimeRecord;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Employee extends Authenticatable
{
    protected $table = 'employees';
    protected $fillable = [
        'employee_no',
        'name',
        'gender',
        'hire_date',
        'department',
        'role',
        'password',
        'is_active',
        'compensatory_hours_remaining',
        'line_user_id',
    ];
    protected $hidden = [
        'password',
    ];
    protected $casts = [
        'hire_date'=> 'date',
        'role' => Role::class,
        'is_active' => 'boolean',
    ];

    public function leaveRequests(){
        return $this->hasMany(LeaveRequest::class);
    }

    public function overtimeRecords(){
        return $this->hasMany(OvertimeRecord::class);
    }

    public function annualLeaveEntitlement():int{
        $years = $this->hire_date->diffInYears(now());
        $months = $this->hire_date->diffInMonths(now());

        if ($months < 6)   return 0;
        if ($years < 1)    return 3;
        if ($years < 2)    return 7;
        if ($years < 3)    return 10;
        if ($years < 5)    return 14;
        if ($years < 10)   return 15;
        // 滿10年後每年+1天，上限30天
        return min(15 + ($years - 10), 30);
    }    

    public function remainingAnnualLeave(): float
    {
        $used = $this->leaveRequests()
            ->where('leave_type', '特休假')
            ->where('status', '已核准')
            ->sum('days');

        return $this->annualLeaveEntitlement() - $used;
    }

    public function usedSickLeave(): float
    {
        return $this->leaveRequests()
            ->where('leave_type', '病假')
            ->where('status', '已核准')
            ->whereYear('start_date', now()->year)
            ->sum('days');
    }

    public function usedPersonalLeave(): float
    {
        return $this->leaveRequests()
            ->where('leave_type', '事假')
            ->where('status', '已核准')
            ->whereYear('start_date', now()->year)
            ->sum('days');
    }

    public function usedMenstrualLeaveThisMonth(): float
    {
        return $this->leaveRequests()
            ->where('leave_type', '生理假')
            ->where('status', '已核准')
            ->whereYear('start_date', now()->year)
            ->whereMonth('start_date', now()->month)
            ->sum('days');
    }
}
