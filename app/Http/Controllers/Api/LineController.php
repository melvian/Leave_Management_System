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
        $shiftEnd          = Carbon::parse($today . ' ' . $shift->shift_end);
        $earlyLeaveMinutes = 0;
        $status            = $record->status;

        if ($now->lt($shiftEnd)) {
            $earlyLeaveMinutes = (int) $now->diffInMinutes($shiftEnd);
            if ($status === 'normal') $status = 'early_leave';
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

    public function getUserId(Request $request)
    {
        $employee = Employee::find($request->employee_id);

        return response()->json([
            'line_user_id' => $employee?->line_user_id
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
}