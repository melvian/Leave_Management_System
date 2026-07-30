<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LeaveRequest;
use App\Models\OvertimeRecord;
use App\Models\Employee;
use App\Services\MqttService;

class ApprovalController extends Controller
{
    public function leaveIndex(Request $request)
    {
        $emp    = Auth::user();
        $role   = $emp->role instanceof \App\Enums\Role ? $emp->role->value : $emp->role;
        $status = $request->get('status', 'pending');

        // Check if user is acting as delegate for a manager
        if (!in_array($role, ['部門主管', '人資部', '系統管理者'])) {
            $delegation = \App\Models\Delegation::with('delegator')
                ->where('delegate_id', $emp->id)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date',   '>=', now()->toDateString())
                ->first();

            if ($delegation) {
                $role = '部門主管';
                $emp  = $delegation->delegator;
            }
        }

        $query = LeaveRequest::with('employee')->where('status', '簽核中');

        if ($status === 'processed') {
            $query = LeaveRequest::with('employee')
                ->whereIn('status', ['已核准', '已拒絕']);
        }

        if ($role === '部門主管') {
            $query->whereHas('employee', fn($q) =>
                $q->where('department', $emp->department))
                ->where('employee_id', '!=', $emp->id);
            if ($status === 'pending') {
                $query->where('current_approver', 'manager');
            }
        } elseif ($role === '人資部') {
            if ($status === 'pending') {
                $query->where('current_approver', 'hr');
            }
        }

        $leaves = $query->orderBy('created_at', 'desc')->get();
        return view('approval.leave-index', compact('leaves', 'emp', 'status'));
    }

    public function leaveShow($id)
    {
        $emp   = Auth::user();
        $leave = LeaveRequest::with('employee')->findOrFail($id);
        return view('approval.leave-show', compact('leave', 'emp'));
    }

    public function leaveApprove(Request $request, $id)
    {
        $emp   = Auth::user();
        $role  = $this->getEffectiveRole($emp);
        $leave = LeaveRequest::with('employee')->findOrFail($id);

        if ($role === '部門主管') {
            if ($leave->days > 3) {
                // Forward to HR — no deduction yet
                $leave->update([
                    'current_approver' => 'hr',
                    'admin_note'       => $request->admin_note,
                ]);
            } else {
                // Final approval — deduct if 補休
                $leave->update([
                    'status'           => '已核准',
                    'current_approver' => null,
                    'admin_note'       => $request->admin_note,
                ]);
                $this->deductIfCompensatory($leave);
            }
        } elseif (in_array($role, ['人資部', '系統管理者'])) {
            // Final approval — deduct if 補休
            $leave->update([
                'status'           => '已核准',
                'current_approver' => null,
                'admin_note'       => $request->admin_note,
            ]);
            $this->deductIfCompensatory($leave);
        }

        $leaveType = $leave->leave_type instanceof \App\Enums\LeaveType
            ? $leave->leave_type->value
            : $leave->leave_type;

        try {
            $mqtt = new MqttService();
            $mqtt->publish('leave/approved', [
                'leave_id'      => $leave->id,
                'employee_id'   => $leave->employee_id,
                'employee_name' => $leave->employee->name,
                'leave_type'    => $leaveType,
                'start_date'    => $leave->start_date->format('Y-m-d'),
                'end_date'      => $leave->end_date->format('Y-m-d'),
                'days'          => $leave->days,
                'approved_by'   => Auth::user()->name,
                'timestamp'     => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('MQTT publish failed: ' . $e->getMessage());
        }

        return redirect()->route('approval.leave.index')
            ->with('success', '申請已處理。');
    }

    // Helper — deducts compensatory hours when 補休 leave is finally approved
    private function deductIfCompensatory(LeaveRequest $leave): void
    {
        $leaveType = $leave->leave_type instanceof \App\Enums\LeaveType
            ? $leave->leave_type->value
            : $leave->leave_type;

        if ($leaveType === '補休') {
            // Use hours field if it's an hourly request, otherwise convert days to hours
            $hoursToDeduct = $leave->hours ?? ($leave->days * 8);
            $leave->employee->decrement('compensatory_hours_remaining', $hoursToDeduct);
        }
    }

    public function leaveReject(Request $request, $id)
    {
        $request->validate([
            'admin_note' => 'required|string|min:1',
        ], [
            'admin_note.required' => '拒絕申請時必須填寫原因。',
        ]);

        $emp   = Auth::user();
        $role  = $this->getEffectiveRole($emp);
        $leave = LeaveRequest::with('employee')->findOrFail($id);

        $leaveType = $leave->leave_type instanceof \App\Enums\LeaveType
            ? $leave->leave_type->value
            : $leave->leave_type;

        $leave->update([
            'status'           => '已拒絕',
            'current_approver' => null,
            'admin_note'       => $request->admin_note,
        ]);

        // ── ADD THIS ────────────────────────────────
        try {
            $mqtt = new MqttService();
            $mqtt->publish('leave/rejected', [
                'leave_id'      => $leave->id,
                'employee_id'   => $leave->employee_id,
                'employee_name' => $leave->employee->name,
                'leave_type'    => $leaveType,
                'admin_note'    => $request->admin_note,
                'rejected_by'   => Auth::user()->name,
                'timestamp'     => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('MQTT publish failed: ' . $e->getMessage());
        }
        // ────────────────────────────────────────────

        return redirect()->route('approval.leave.index')
            ->with('success', '申請已拒絕。');
    }

    public function overtimeIndex(Request $request)
    {
        $emp    = Auth::user();
        $role   = $emp->role instanceof \App\Enums\Role
                    ? $emp->role->value
                    : $emp->role;
        $status = $request->get('status', 'pending');

        // Check if user is acting as delegate for a manager
        if (!in_array($role, ['部門主管', '人資部', '系統管理者'])) {
            $delegation = \App\Models\Delegation::with('delegator')
                ->where('delegate_id', $emp->id)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date',   '>=', now()->toDateString())
                ->first();

            if ($delegation) {
                $role = '部門主管';
                $emp  = $delegation->delegator;
            }
        }

        $query = OvertimeRecord::with('employee');

        if ($status === 'pending') {
            $query->where('status', '待確認');
        } else {
            $query->whereIn('status', ['已確認', '已駁回']);
        }

        if ($role === '部門主管') {
            $query->whereHas('employee', fn($q) =>
                $q->where('department', $emp->department))
                ->where('employee_id', '!=', $emp->id);
        }

        $records = $query->orderBy('date', 'desc')->get();
        return view('approval.overtime-index', compact('records', 'emp', 'status'));
    }

    public function overtimeShow($id)
    {
        $emp    = Auth::user();
        $record = OvertimeRecord::with('employee')->findOrFail($id);
        return view('approval.overtime-show', compact('record', 'emp'));
    }

    public function overtimeConfirm(Request $request, $id)
    {
        $emp    = Auth::user();
        $role   = $this->getEffectiveRole($emp);
        $record = OvertimeRecord::with('employee')->findOrFail($id);

        $record->update([
            'status'     => '已確認',
            'admin_note' => $request->admin_note,
        ]);

        // Add hours to employee compensatory balance
        $record->employee->increment('compensatory_hours_remaining', $record->hours);

        try {
            $mqtt = new MqttService();
            $mqtt->publish('overtime/confirmed', [
                'employee_id'   => $record->employee_id,
                'employee_name' => $record->employee->name,
                'employee_no'   => $record->employee->employee_no,
                'hours'         => $record->hours,
                'date'          => $record->date->format('Y-m-d'),
                'confirmed_by'  => Auth::user()->name,
                'timestamp'     => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('MQTT publish failed: ' . $e->getMessage());
        }

        return redirect()->route('approval.overtime.index')
            ->with('success', '加班記錄已確認，補休時數已更新。');
    }

    public function overtimeReject(Request $request, $id)
    {
        $emp    = Auth::user();
        $role   = $this->getEffectiveRole($emp);
        $record = OvertimeRecord::findOrFail($id);
        $record->update([
            'status'     => '已駁回',
            'admin_note' => $request->admin_note,
        ]);
        return redirect()->route('approval.overtime.index')
            ->with('success', '加班記錄已駁回。');
    }

    private function getEffectiveRole(Employee $emp): string
    {
        $role = $emp->role instanceof \App\Enums\Role ? $emp->role->value : $emp->role;
        if (!in_array($role, ['部門主管', '人資部', '系統管理者'])) {
            $isDelegating = \App\Models\Delegation::where('delegate_id', $emp->id)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date',   '>=', now()->toDateString())
                ->exists();

            if ($isDelegating) {
                return '部門主管';
            }
        }
        return $role;
    }
}
