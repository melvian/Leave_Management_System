<?php

namespace App\Http\Controllers;

#use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $employee = Auth::user();

        // Leave balances
        $balances = [
            'annual_entitlement'  => $employee->annualLeaveEntitlement(),
            'annual_remaining'    => $employee->remainingAnnualLeave(),
            'sick_used'           => $employee->usedSickLeave(),
            'personal_used'       => $employee->usedPersonalLeave(),
            'menstrual_used'      => $employee->gender === 'female'
                                        ? $employee->usedMenstrualLeaveThisMonth()
                                        : null,
            'compensatory_hours'  => $employee->compensatory_hours_remaining,
        ];

        // Recent 3 requests
        $recentLeave = $employee->leaveRequests()
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        $recentOvertime = $employee->overtimeRecords()
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        // Pending approvals count (manager/hr only)
        $pendingCount = 0;
        $role = $employee->role instanceof \App\Enums\Role
            ? $employee->role->value
            : $employee->role;

        if ($role === '部門主管') {
            $pendingCount = \App\Models\LeaveRequest::where('status', '簽核中')
                ->where('current_approver', 'manager')
                ->whereHas('employee', fn($q) => $q->where('department', $employee->department))
                ->count();
            $pendingCount += \App\Models\OvertimeRecord::where('status', '待確認')
                ->whereHas('employee', fn($q) => $q->where('department', $employee->department))
                ->count();
        } elseif ($role === '人資部' || $role === '系統管理者') {
            $pendingCount = \App\Models\LeaveRequest::where('status', '簽核中')
                ->where('current_approver', 'hr')
                ->count();
        }

        return view('dashboard.index', compact(
            'employee', 'balances', 'recentLeave', 'recentOvertime', 'pendingCount'
        ));
    }
}
