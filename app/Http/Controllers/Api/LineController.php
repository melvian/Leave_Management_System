<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Services\MqttService;
use Carbon\Carbon;

class LineController extends Controller
{
    public function clockIn(Request $request)
    {
        $employee = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '找不到對應的員工帳號，請聯繫人資部綁定您的 Line 帳號。'
            ]);
        }

        $now   = now();
        $today = $now->toDateString();
        $shift = \App\Models\ShiftSetting::forDepartment($employee->department);

        // Weekend check
        if ($now->isWeekend()) {
            return response()->json([
                'success' => false,
                'message' => '今日為週末，無需打卡。'
            ]);
        }

        // Holiday check
        $holiday = \App\Models\Holiday::whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)->first();
        if ($holiday) {
            return response()->json([
                'success' => false,
                'message' => "今日為假日（{$holiday->name}），無需打卡。"
            ]);
        }

        // Already clocked in
        $existing = AttendanceRecord::whereDate('date', $today)
            ->where('employee_id', $employee->id)->first();
        if ($existing && $existing->clock_in) {
            return response()->json([
                'success' => false,
                'message' => '今日已打卡上班。'
            ]);
        }

        // Calculate late
        $shiftStart  = Carbon::parse($today . ' ' . $shift->shift_start);
        $lateMinutes = 0;
        $status      = 'normal';

        if ($now->gt($shiftStart->copy()->addMinutes($shift->late_tolerance))) {
            $lateMinutes = (int) $shiftStart->diffInMinutes($now);
            $status      = 'late';
        }

        if ($existing) {
            $existing->update([
                'clock_in'     => $now,
                'late_minutes' => $lateMinutes,
                'status'       => $status,
            ]);
        } else {
            AttendanceRecord::create([
                'employee_id'  => $employee->id,
                'date'         => $today,
                'clock_in'     => $now,
                'late_minutes' => $lateMinutes,
                'status'       => $status,
            ]);
        }

        // Publish MQTT
        try {
            $mqtt = new MqttService();
            $mqtt->publish('attendance/clock-in', [
                'employee_id'   => $employee->id,
                'employee_name' => $employee->name,
                'employee_no'   => $employee->employee_no,
                'department'    => $employee->department,
                'clock_in'      => $now->toIso8601String(),
                'late_minutes'  => $lateMinutes,
                'status'        => $status,
                'source'        => 'line',
                'timestamp'     => $now->toIso8601String(),
            ]);
        } catch (\Exception $e) {}

        return response()->json([
            'success'      => true,
            'clock_in'     => $now->format('H:i'),
            'late_minutes' => $lateMinutes,
            'status'       => $status,
        ]);
    }

    public function clockOut(Request $request)
    {
        $employee = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '找不到對應的員工帳號。'
            ]);
        }

        $now    = now();
        $today  = $now->toDateString();
        $shift  = \App\Models\ShiftSetting::forDepartment($employee->department);
        $record = AttendanceRecord::whereDate('date', $today)
            ->where('employee_id', $employee->id)->first();

        if (!$record || !$record->clock_in) {
            return response()->json([
                'success' => false,
                'message' => '請先打卡上班。'
            ]);
        }

        if ($record->clock_out) {
            return response()->json([
                'success' => false,
                'message' => '今日已打卡下班。'
            ]);
        }

        // Calculate worked hours
        $clockIn   = Carbon::parse($record->clock_in);
        $totalMins = $clockIn->diffInMinutes($now);

        $lunchStart   = Carbon::parse($today . ' 12:00');
        $lunchEnd     = Carbon::parse($today . ' 13:00');
        $overlapStart = max($clockIn->timestamp, $lunchStart->timestamp);
        $overlapEnd   = min($now->timestamp, $lunchEnd->timestamp);
        if ($overlapEnd > $overlapStart) {
            $totalMins -= ($overlapEnd - $overlapStart) / 60;
        }

        $workedHours       = round($totalMins / 60, 2);
        $shiftEndTime      = Carbon::parse($today . ' ' . $shift->shift_end);
        $earlyLeaveMinutes = 0;
        $overtimeMinutes   = 0;
        $status            = $record->status;

        // Early leave check
        if ($now->lt($shiftEndTime)) {
            $minutesEarly = (int) $now->diffInMinutes($shiftEndTime);
            if ($minutesEarly > $shift->late_tolerance) {
                $earlyLeaveMinutes = $minutesEarly;
                if ($status === 'normal') $status = 'early_leave';
            }
        }

        // Overtime check
        if ($now->gt($shiftEndTime)) {
            $minutesOver = (int) $shiftEndTime->diffInMinutes($now);
            if ($minutesOver > $shift->late_tolerance) {
                $overtimeMinutes = $minutesOver;
            }
        }

        $record->update([
            'clock_out'           => $now,
            'worked_hours'        => $workedHours,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'status'              => $status,
        ]);

        try {
            $mqtt = new MqttService();
            $mqtt->publish('attendance/clock-out', [
                'employee_id'         => $employee->id,
                'employee_name'       => $employee->name,
                'employee_no'         => $employee->employee_no,
                'department'          => $employee->department,
                'clock_out'           => $now->toIso8601String(),
                'worked_hours'        => $workedHours,
                'early_leave_minutes' => $earlyLeaveMinutes,
                'status'              => $status,
                'source'              => 'line',
                'timestamp'           => $now->toIso8601String(),
            ]);
        } catch (\Exception $e) {}

        return response()->json([
            'success'             => true,
            'clock_out'           => $now->format('H:i'),
            'worked_hours'        => $workedHours,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_minutes'    => $overtimeMinutes,
            'shift_end'           => $shift->shift_end,
            'date'                => $today,
        ]);
    }

    public function balance(Request $request)
    {
        $employee = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '找不到對應的員工帳號。'
            ]);
        }

        $data = [
            'success'            => true,
            'name'               => $employee->name,
            'annual_remaining'   => $employee->remainingAnnualLeave(),
            'compensatory_hours' => $employee->compensatory_hours_remaining,
            'sick_used'          => $employee->usedSickLeave(),
            'personal_used'      => $employee->usedPersonalLeave(),
            'gender'             => $employee->gender,
        ];

        if ($employee->gender === 'female') {
            $data['menstrual_used'] = $employee->usedMenstrualLeaveThisMonth();
        }

        return response()->json($data);
    }

    public function myLeaves(Request $request)
    {
        $employee = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '找不到員工帳號。'
            ]);
        }

        $leaves = \App\Models\LeaveRequest::where('employee_id', $employee->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($leave) {
                $type   = $leave->leave_type instanceof \App\Enums\LeaveType
                    ? $leave->leave_type->value : $leave->leave_type;
                $status = $leave->status instanceof \App\Enums\LeaveStatus
                    ? $leave->status->value : $leave->status;

                return [
                    'id'         => $leave->id,
                    'leave_type' => $type,
                    'start_date' => $leave->start_date->format('Y-m-d'),
                    'end_date'   => $leave->end_date->format('Y-m-d'),
                    'days'       => $leave->days,
                    'status'     => $status,
                    'admin_note' => $leave->admin_note,
                ];
            });

        return response()->json([
            'success' => true,
            'name'    => $employee->name,
            'leaves'  => $leaves,
        ]);
    }

    public function myOvertime(Request $request)
    {
        $employee = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '找不到員工帳號。'
            ]);
        }

        $records = \App\Models\OvertimeRecord::where('employee_id', $employee->id)
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($r) {
                $status = $r->status instanceof \App\Enums\OvertimeStatus
                    ? $r->status->value : $r->status;
                return [
                    'id'             => $r->id,
                    'date'           => $r->date->format('Y-m-d'),
                    'start_time'     => $r->start_time,
                    'end_time'       => $r->end_time,
                    'hours'          => $r->hours,
                    'overtime_reason'=> $r->overtime_reason,
                    'status'         => $status,
                    'admin_note'     => $r->admin_note,
                ];
            });

        return response()->json([
            'success' => true,
            'name'    => $employee->name,
            'records' => $records,
        ]);
    }

    public function getUserId(Request $request)
    {
        $employee = Employee::find($request->employee_id);

        $roleValue = $employee?->role instanceof \App\Enums\Role
            ? $employee->role->value
            : $employee?->role;

        return response()->json([
            'line_user_id' => $employee?->line_user_id,
            'role'         => $roleValue,
            'name'         => $employee?->name,
        ]);
    }

    public function getManagerLineId(Request $request)
    {
        $manager = Employee::where('department', $request->department)
            ->where('role', '部門主管')
            ->where('is_active', true)
            ->first();

        return response()->json([
            'line_user_id' => $manager?->line_user_id,
            'name'         => $manager?->name,
        ]);
    }

    public function getHrLineId()
    {
        // Find any active HR employee with a Line ID
        $hr = Employee::where('role', '人資部')
            ->where('is_active', true)
            ->whereNotNull('line_user_id')
            ->first();

        return response()->json([
            'line_user_id' => $hr?->line_user_id,
            'name'         => $hr?->name,
        ]);
    }

    public function lineApprove(Request $request)
    {
        \Log::info('lineApprove called', [
            'manager_line_id' => $request->manager_line_id,
            'leave_id'        => $request->leave_id,
        ]);

        $manager = Employee::where('line_user_id', $request->manager_line_id)
            ->where('role', '部門主管')
            ->first();

        \Log::info('manager lookup result', [
            'found' => $manager ? $manager->name : 'null'
        ]);

        if (!$manager) {
            return response()->json([
                'success' => false,
                'message' => '無審核權限。'
            ]);
        }

        $leave = \App\Models\LeaveRequest::with('employee')->find($request->leave_id);

        if ($leave && $leave->employee_id === $manager->id) {
            return response()->json([
                'success' => false,
                'message' => '主管不能核准自己的請假申請。'
            ]);
        }

        \Log::info('leave lookup result', [
            'found'  => $leave ? 'yes' : 'null',
            'status' => $leave?->status,
        ]);

        $leaveStatus = $leave->status instanceof \App\Enums\LeaveStatus
            ? $leave->status->value: $leave->status;

        if (!$leave || $leaveStatus !== '簽核中') {
            return response()->json([
                'success' => false,
                'message' => '找不到此假單或假單狀態已變更。'
            ]);
        }

        if ($leave->days > 3) {
            $leave->update([
                'current_approver' => 'hr',
                'admin_note'       => '主管已透過 Line 核准，轉送人資部最終審核。',
            ]);
        } else {
            $leave->update([
                'status'           => '已核准',
                'current_approver' => null,
                'admin_note'       => '主管透過 Line 核准。',
            ]);

            // Deduct compensatory if needed
            $leaveType = $leave->leave_type instanceof \App\Enums\LeaveType
                ? $leave->leave_type->value : $leave->leave_type;
            if ($leaveType === '補休') {
                $hoursToDeduct = $leave->hours ?? ($leave->days * 8);
                $leave->employee->decrement('compensatory_hours_remaining', $hoursToDeduct);
            }
        }

        try {
            $mqtt = new \App\Services\MqttService();
            $mqtt->publish('leave/approved', [
                'leave_id'      => $leave->id,
                'employee_id'   => $leave->employee_id,
                'employee_name' => $leave->employee->name,
                'leave_type'    => $leave->leave_type instanceof \App\Enums\LeaveType
                                    ? $leave->leave_type->value : $leave->leave_type,
                'start_date'    => $leave->start_date->format('Y-m-d'),
                'end_date'      => $leave->end_date->format('Y-m-d'),
                'days'          => $leave->days,
                'approved_by'   => $manager->name,
                'timestamp'     => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {}

        return response()->json(['success' => true]);
    }

    public function lineReject(Request $request)
    {
        $manager = Employee::where('line_user_id', $request->manager_line_id)
            ->where('role', '部門主管')
            ->first();

        if (!$manager) {
            return response()->json([
                'success' => false,
                'message' => '無審核權限。'
            ]);
        }

        $leave = \App\Models\LeaveRequest::with('employee')->find($request->leave_id);

        if ($leave && $leave->employee_id === $manager->id) {
            return response()->json([
                'success' => false,
                'message' => '主管不能拒絕自己的請假申請。'
            ]);
        }

        $leaveStatus = $leave->status instanceof \App\Enums\LeaveStatus
            ? $leave->status->value
            : $leave->status;

        if (!$leave || $leaveStatus !== '簽核中') {
            return response()->json([
                'success' => false,
                'message' => '找不到此假單或假單狀態已變更。'
            ]);
        }

        $leaveType = $leave->leave_type instanceof \App\Enums\LeaveType
            ? $leave->leave_type->value : $leave->leave_type;

        $leave->update([
            'status'           => '已拒絕',
            'current_approver' => null,
            'admin_note'       => $request->admin_note,
        ]);

        try {
            $mqtt = new \App\Services\MqttService();
            $mqtt->publish('leave/rejected', [
                'leave_id'      => $leave->id,
                'employee_id'   => $leave->employee_id,
                'employee_name' => $leave->employee->name,
                'leave_type'    => $leaveType,
                'admin_note'    => $request->admin_note,
                'rejected_by'   => $manager->name,
                'timestamp'     => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {}

        return response()->json(['success' => true]);
    }

    public function lineLeaveSubmit(Request $request)
    {
        $employee = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '找不到員工帳號。'
            ]);
        }

        $start = \Carbon\Carbon::parse($request->start_date);
        $end   = \Carbon\Carbon::parse($request->end_date);

        // Same-day after 12pm check
        if ($start->isToday() && now()->hour >= 12) {
            return response()->json([
                'success' => false,
                'message' => '當日請假須於中午12:00前提出申請。'
            ]);
        }

        // Calculate days/hours
        $hours = null;
        $days  = 0;

        if ($request->filled('start_time') && $request->filled('end_time')
            && $request->start_date === $request->end_date) {
            // Hourly leave
            $hours = $request->hours;
            $days  = round($hours / 8, 2);
        } else {
            // Full day — count weekdays
            $current = $start->copy();
            while ($current->lte($end)) {
                if ($current->isWeekday()) $days++;
                $current->addDay();
            }
        }

        // Overlap check
        $overlap = \App\Models\LeaveRequest::where('employee_id', $employee->id)
            ->whereNotIn('status', ['草稿', '已拒絕'])
            ->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                ->orWhere(function ($q2) use ($request) {
                    $q2->where('start_date', '<=', $request->start_date)
                        ->where('end_date', '>=', $request->end_date);
                });
            })->first();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => '所選日期與現有假單重疊，請重新選擇。'
            ]);
        }

        // Balance check
        $leaveType = $request->leave_type;
        if ($leaveType === '特休假' && $days > $employee->remainingAnnualLeave()) {
            return response()->json([
                'success' => false,
                'message' => "特休假餘額不足，目前剩餘 {$employee->remainingAnnualLeave()} 天。"
            ]);
        }
        if ($leaveType === '病假' && ($employee->usedSickLeave() + $days) > 30) {
            return response()->json(['success' => false, 'message' => '病假已超過年度上限30天。']);
        }
        if ($leaveType === '事假' && ($employee->usedPersonalLeave() + $days) > 14) {
            return response()->json(['success' => false, 'message' => '事假已超過年度上限14天。']);
        }
        if ($leaveType === '生理假' && $employee->usedMenstrualLeaveThisMonth() >= 1) {
            return response()->json(['success' => false, 'message' => '本月生理假已請畢。']);
        }
        if ($leaveType === '補休' && ($days * 8) > $employee->compensatory_hours_remaining) {
            return response()->json([
                'success' => false,
                'message' => "補休時數不足，目前剩餘 {$employee->compensatory_hours_remaining} 小時。"
            ]);
        }

        // Determine approver based on role
        $roleValue = $employee->role instanceof \App\Enums\Role
            ? $employee->role->value : $employee->role;

        $currentApprover = match($roleValue) {
            '部門主管' => 'hr',
            '人資部'   => 'hr',
            default   => 'manager',
        };

        $leaveRequest = \App\Models\LeaveRequest::create([
            'employee_id'      => $employee->id,
            'leave_type'       => $request->leave_type,
            'leave_reason'     => $request->leave_reason,
            'start_date'       => $request->start_date,
            'end_date'         => $request->end_date,
            'days'             => $days,
            'hours'            => $hours,
            'start_time'       => $request->start_time ?? null,
            'end_time'         => $request->end_time ?? null,
            'status'           => '簽核中',
            'current_approver' => $currentApprover,
            'admin_note'       => null,
        ]);

        // Publish MQTT so manager gets Line notification
        try {
            $mqtt = new \App\Services\MqttService();
            $mqtt->publish('leave/submitted', [
                'leave_id'      => $leaveRequest->id,
                'employee_id'   => $employee->id,
                'employee_name' => $employee->name,
                'employee_no'   => $employee->employee_no,
                'employee_line_id' => $employee->line_user_id ?? null,
                'department'    => $employee->department,
                'leave_type'    => $request->leave_type,
                'start_date'    => $request->start_date,
                'end_date'      => $request->end_date,
                'days'          => $days,
                'hours'         => $hours,
                'timestamp'     => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {}

        return response()->json(['success' => true]);
    }

    public function lineOvertimeSubmit(Request $request)
    {
        \Log::info('lineOvertimeSubmit received', $request->all());
        $employee = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '找不到員工帳號。'
            ]);
        }

        if ($request->hours <= 0) {
            return response()->json([
                'success' => false,
                'message' => '加班時數必須大於0。'
            ]);
        }

        $record = \App\Models\OvertimeRecord::create([
            'employee_id'  => $employee->id,
            'date'         => $request->date,
            'start_time'   => $request->start_time,
            'end_time'     => $request->end_time,
            'hours'        => $request->hours,
            'overtime_reason'=> $request->overtime_reason,
            'status'       => '待確認',
            'admin_note'   => null,
        ]);

        // Publish MQTT 
        try {
            $mqtt = new \App\Services\MqttService();
            $mqtt->publish('overtime/submitted', [
                'overtime_id'   => $record->id,
                'employee_id'   => $employee->id,
                'employee_name' => $employee->name,
                'employee_no'   => $employee->employee_no,
                'department'    => $employee->department,
                'date'          => $request->date,
                'start_time'    => $request->start_time,
                'end_time'      => $request->end_time,
                'hours'         => $request->hours,
                'overtime_reason' => $request->overtime_reason,
                'timestamp'     => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {}

        return response()->json(['success' => true]);
    }   

    public function PendingLeaves(Request $request)
    {
        $employee = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)->first();

        if (!$employee) {
            return response()->json(['success' => false,
                'message' => '找不到員工帳號。']);
        }

        $roleValue = $employee->role instanceof \App\Enums\Role
            ? $employee->role->value : $employee->role;

        $effectiveDept = $employee->department;
        $effectiveRole = $roleValue;

        // Check delegation
        if (!in_array($roleValue, ['部門主管', '人資部', '系統管理者'])) {
            $delegation = \App\Models\Delegation::with('delegator')
                ->where('delegate_id', $employee->id)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date',   '>=', now()->toDateString())
                ->first();

            if ($delegation) {
                $effectiveRole = '部門主管';
                $effectiveDept = $delegation->delegator->department;
            }
        }

        if (!in_array($effectiveRole, ['部門主管', '人資部', '系統管理者'])) {
            return response()->json(['success' => false,
                'message' => '您沒有審核權限。']);
        }

        $query = \App\Models\LeaveRequest::with('employee')
            ->where('status', '簽核中');

        if ($effectiveRole === '部門主管') {
            $query->whereHas('employee', fn ($q) => $q
                ->where('department', $effectiveDept))
                ->where('employee_id', '!=', $employee->id)
                ->where('current_approver', 'manager');
        } elseif (in_array($effectiveRole, ['人資部','系統管理者'])) {
            $query->where('current_approver', 'hr');
        }

        $leaves = $query->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($leave) {
                $type   = $leave->leave_type instanceof \App\Enums\LeaveType
                    ? $leave->leave_type->value : $leave->leave_type;
                return [
                    'id'            => $leave->id,
                    'employee_name' => $leave->employee->name,
                    'employee_no'   => $leave->employee->employee_no,
                    'department'    => $leave->employee->department,
                    'leave_type'    => $type,
                    'start_date'    => $leave->start_date->format('Y-m-d'),
                    'end_date'      => $leave->end_date->format('Y-m-d'),
                    'days'          => $leave->days,
                    'leave_reason'  => $leave->leave_reason,
                ];
            });

        return response()->json([
            'success'    => true,
            'leaves'     => $leaves,
            'role'       => $effectiveRole,
            'department' => $effectiveDept,
        ]);
    }

    public function PendingOvertime(Request $request)
    {
        $employee = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)->first();

        if (!$employee) {
            return response()->json(['success' => false,
                'message' => '找不到員工帳號。']);
        }

        $roleValue = $employee->role instanceof \App\Enums\Role
            ? $employee->role->value : $employee->role;

        $effectiveDept = $employee->department;
        $effectiveRole = $roleValue;

        // Check delegation
        if (!in_array($roleValue, ['部門主管', '人資部', '系統管理者'])) {
            $delegation = \App\Models\Delegation::with('delegator')
                ->where('delegate_id', $employee->id)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date',   '>=', now()->toDateString())
                ->first();

            if ($delegation) {
                $effectiveRole = '部門主管';
                $effectiveDept = $delegation->delegator->department;
            }
        }

        if (!in_array($effectiveRole, ['部門主管', '人資部', '系統管理者'])) {
            return response()->json(['success' => false,
                'message' => '您沒有審核權限。']);
        }

        $query = \App\Models\OvertimeRecord::with('employee')
            ->where('status', '待確認');
        
        if ($effectiveRole === '部門主管') {
            $query->whereHas('employee', fn ($q) => $q
                ->where('department', $effectiveDept))
                ->where('employee_id', '!=', $employee->id);
        } 

        $records = $query->orderBy('date', 'asc')
            ->get()
            ->map(function ($record) {
                return [
                    'id'            => $record->id,
                    'employee_name' => $record->employee->name,
                    'employee_no'   => $record->employee->employee_no,
                    'department'    => $record->employee->department,
                    'date'          => $record->date->format('Y-m-d'),
                    'start_time'    => $record->start_time,
                    'end_time'      => $record->end_time,
                    'hours'         => $record->hours,
                    'overtime_reason'=> $record->overtime_reason,
                ];
            });

        return response()->json([
            'success'    => true,
            'records'    => $records,
            'role'       => $effectiveRole,
        ]);
    }

    public function lineOvertimeConfirm(Request $request)
    {
        $managerEmp = Employee::where('line_user_id', $request->manager_line_id)
            ->where('is_active', true)->first();

        $manager = null;
        if ($managerEmp) {
            $managerRole = $managerEmp->role instanceof \App\Enums\Role
                ? $managerEmp->role->value : $managerEmp->role;
            if (in_array($managerRole, ['部門主管', '人資部', '系統管理者'])) {
                $manager = $managerEmp;
            }
        }

        if (!$manager) {
            $delegation = \App\Models\Delegation::with('delegator')
                ->where('delegate', fn($q) => $q->where('line_user_id', $request->manager_line_id))
                ->where('is_active', true)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date', '>=', now()->toDateString())
                ->first();

            if ($delegation) {
                $manager = $delegation->delegator;
            }
        }

        if ($manager){
            return response()->json([
                'success' => false,
                'message' => '您沒有審核權限。'
            ]);
        }

        $record = \App\Models\OvertimeRecord::with('employee')
            ->find($request->overtime_id);

        if (!$record ) {
            return response()->json([
                'success' => false,
                'message' => '找不到此加班申請。'
            ]);
        }

        $otStatus = $record->status instanceof \App\Enums\OvertimeStatus
            ? $record->status->value : $record->status;

        if ($otStatus !== '待確認') {
            return response()->json([
                'success' => false,
                'message' => '加班申請狀態已變更。'
            ]);
        }

        $record->update([
            'status'     => '已確認',
            'admin_note' => $request->admin_note,
        ]);

        $record->employee->increment('compensatory_hours_remaining', $record->hours);
        try {
            $mqtt = new \App\Services\MqttService();
            $mqtt->publish('overtime/confirmed', [
                'employee_id'   => $record->employee_id,
                'employee_name' => $record->employee->name,
                'employee_no'   => $record->employee->employee_no,
                'date'          => $record->date->format('Y-m-d'),
                'start_time'    => $record->start_time,
                'end_time'      => $record->end_time,
                'hours'         => $record->hours,
                'admin_note'    => $request->admin_note,
                'confirmed_by'  => $manager?->name ?? '代理主管',
                'timestamp'     => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {}

        try {
            $empLineId = $record->employee->line_user_id;
            if ($empLineId) {
                $lineClient = new \LINE\LINEBot(
                    new \LINE\LINEBot\HTTPClient\CurlHTTPClient(env('LINE_CHANNEL_ACCESS_TOKEN')),
                    ['channelSecret' => env('LINE_CHANNEL_SECRET'),
                ]);
            }
        } catch (\Exception $e) {}

        return response()->json(['success' => true]);
    }

    public function  lineOvertimeReject (request $request){
        $managerEmp = Employee::where('line_user_id', $request->manager_line_id)
            ->where('is_active', true)->first();

        $manager = null;
        if ($managerEmp) {
            $managerRole = $managerEmp->role instanceof \App\Enums\Role
                ? $managerEmp->role->value : $managerEmp->role;
            if (in_array($managerRole, ['部門主管', '人資部', '系統管理者'])) {
                $manager = $managerEmp;
            }
        }

        if (!$manager) {
            $delegation = \App\Models\Delegation::with('delegator')
                ->where('delegate', fn($q) => $q
                ->where('line_user_id', $request->manager_line_id))
                ->where('is_active', true)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date', '>=', now()->toDateString())
                ->first();

            if ($delegation) {
                $manager = $delegation->delegator;
            }
        }

        if ($manager){
            return response()->json([
                'success' => false,
                'message' => '無拒絕權限。'
            ]);
        }

        $record = \App\Models\OvertimeRecord::find($request->overtime_id);

        if (!$record ) {
            return response()->json([
                'success' => false,
                'message' => '找不到此加班申請。'
            ]);
        }

        $otStatus = $record->status instanceof \App\Enums\OvertimeStatus
            ? $record->status->value : $record->status;

        if ($otStatus !== '待確認') {
            return response()->json([
                'success' => false,
                'message' => '加班申請狀態已變更。'
            ]);
        }

        $record->update([
            'status'     => '已拒絕',
            'admin_note' => $request->admin_note,
        ]);

        try {
            $mqtt = new \App\Services\MqttService();
            $mqtt->publish('overtime/rejected', [
                'employee_id'   => $record->employee_id,
                'employee_name' => $record->employee->name,
                'employee_no'   => $record->employee->employee_no,
                'date'          => $record->date->format('Y-m-d'),
                'start_time'    => $record->start_time,
                'end_time'      => $record->end_time,
                'hours'         => $record->hours,
                'admin_note'    => $request->admin_note,
            ]);
        } catch (\Exception $e) {}
    }

    public function myProfile(Request $request)
    {
        $employee = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)->first();

        if (!$employee) {
            return response()->json(['success' => false,
                'message' => '找不到員工帳號。']);
        }

        $roleValue = $employee->role instanceof \App\Enums\Role
            ? $employee->role->value : $employee->role;

        return response()->json([
            'success'     => true,
            'employee_id' => $employee->id,
            'name'        => $employee->name,
            'role'        => $roleValue,
            'department'  => $employee->department,
        ]);
    }

    public function employeeByNo(Request $request)
    {
        $employee = Employee::where('employee_no', $request->employee_no)
            ->where('is_active', true)->first();

        if (!$employee) {
            return response()->json(['success' => false,
                'message' => '找不到員工。']);
        }

        return response()->json([
            'success'     => true,
            'employee_id' => $employee->id,
            'name'        => $employee->name,
            'employee_no' => $employee->employee_no,
        ]);
    }

    public function lineSetDelegation(Request $request)
    {
        $manager = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)->first();

        if (!$manager) {
            return response()->json(['success' => false,
                'message' => '找不到員工帳號。']);
        }

        $roleValue = $manager->role instanceof \App\Enums\Role
            ? $manager->role->value : $manager->role;

        if ($roleValue !== '部門主管') {
            return response()->json(['success' => false,
                'message' => '只有部門主管可以設定簽核代理。']);
        }

        // Check overlap
        $overlap = \App\Models\Delegation::where('delegator_id', $manager->id)
            ->where('is_active', true)
            ->whereDate('end_date', '>=', $request->start_date)
            ->whereDate('start_date', '<=', $request->end_date)
            ->exists();

        if ($overlap) {
            return response()->json(['success' => false,
                'message' => '此期間已有有效的代理設定，請先撤銷現有代理或調整日期。']);
        }

        \App\Models\Delegation::create([
            'delegator_id' => $manager->id,
            'delegate_id'  => $request->delegate_id,
            'start_date'   => $request->start_date,
            'end_date'     => $request->end_date,
            'reason'       => $request->reason ?: null,
            'is_active'    => true,
        ]);

        try {
            $delegate = Employee::find($request->delegate_id);
            $mqtt = new \App\Services\MqttService();
            $mqtt->publish('delegation/set', [
                'delegator_id'   => $manager->id,
                'delegator_name' => $manager->name,
                'delegator_dept' => $manager->department,
                'delegate_id'    => $request->delegate_id,
                'delegate_name'  => $delegate?->name,
                'delegate_no'    => $delegate?->employee_no,
                'start_date'     => $request->start_date,
                'end_date'       => $request->end_date,
                'reason'         => $request->reason ?: '',
                'source'         => 'line',
                'timestamp'      => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('MQTT delegation/set failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }

    public function lineRevokeDelegation(Request $request)
    {
        $manager = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)->first();

        if (!$manager) {
            return response()->json(['success' => false,
                'message' => '找不到員工帳號。']);
        }

        $delegation = \App\Models\Delegation::where('id', $request->delegation_id)
            ->where('delegator_id', $manager->id)
            ->first();

        if (!$delegation) {
            return response()->json(['success' => false,
                'message' => '找不到此代理設定或您無權撤銷。']);
        }

        $delegation->update(['is_active' => false]);

        try {
            $mqtt = new \App\Services\MqttService();
            $mqtt->publish('delegation/revoked', [
                'delegator_id'   => $manager->id,
                'delegator_name' => $manager->name,
                'delegator_dept' => $manager->department,
                'delegate_name'  => $delegation->delegate->name ?? '',
                'timestamp'      => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('MQTT delegation/revoked failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }

    public function myDelegations(Request $request)
    {
        $manager = Employee::where('line_user_id', $request->line_user_id)
            ->where('is_active', true)->first();

        if (!$manager) {
            return response()->json(['success' => false,
                'message' => '找不到員工帳號。']);
        }

        $roleValue = $manager->role instanceof \App\Enums\Role
            ? $manager->role->value : $manager->role;

        if ($roleValue !== '部門主管') {
            return response()->json(['success' => false,
                'message' => '只有部門主管可以查看代理設定。']);
        }

        $today       = now()->toDateString();
        $delegations = \App\Models\Delegation::with('delegate')
            ->where('delegator_id', $manager->id)
            ->where('is_active', true)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('start_date')
            ->get()
            ->map(function ($d) use ($today) {
                return [
                    'id'            => $d->id,
                    'delegate_name' => $d->delegate->name,
                    'delegate_no'   => $d->delegate->employee_no,
                    'start_date'    => $d->start_date->format('Y-m-d'),
                    'end_date'      => $d->end_date->format('Y-m-d'),
                    'reason'        => $d->reason,
                    'is_active_now' => $d->start_date->toDateString() <= $today
                        && $d->end_date->toDateString() >= $today,
                ];
            });

        return response()->json([
            'success'     => true,
            'delegations' => $delegations,
        ]);
    }
}