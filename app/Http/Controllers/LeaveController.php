<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LeaveRequest;
use App\Enums\LeaveType;
use App\Services\MqttService;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $employee = Auth::user();
        $myRole   = $employee->role instanceof \App\Enums\Role
            ? $employee->role->value : $employee->role;

        $query = $employee->leaveRequests()->orderBy('created_at', 'desc');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $leaves = $query->get();

        // Pass delegation data for managers
        $myDelegations   = collect();
        $allEmployees    = collect();
        $isManager       = $myRole === '部門主管';

        if ($isManager) {
            $myDelegations = \App\Models\Delegation::with('delegate')
                ->where('delegator_id', $employee->id)
                ->orderBy('start_date', 'desc')
                ->get();

            $allEmployees = \App\Models\Employee::where('id', '!=', $employee->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return view('leave.index', compact(
            'leaves', 'employee', 'isManager', 'myDelegations', 'allEmployees'
        ));
    }

    public function create()
    {
        $employee = Auth::user();
        $leaveTypes = LeaveType::cases();
        $balances = [
            'annual_remaining'   => $employee->remainingAnnualLeave(),
            'sick_used'          => $employee->usedSickLeave(),
            'personal_used'      => $employee->usedPersonalLeave(),
            'menstrual_used'     => $employee->gender === 'female'
                                      ? $employee->usedMenstrualLeaveThisMonth()
                                      : null,
            'compensatory_hours' => $employee->compensatory_hours_remaining,
        ];
        return view('leave.create', compact('employee', 'leaveTypes', 'balances'));
    }

    public function store(Request $request)
    {
        $employee = Auth::user();

        $request->validate([
            'leave_type'   => 'required',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'leave_reason' => 'required|string|max:500',
            'action'       => 'required|in:draft,submit',
            'start_time'   => 'nullable|date_format:H:i',
            'end_time'     => 'nullable|date_format:H:i',
        ]);

        $start = \Carbon\Carbon::parse($request->start_date);
        $end   = \Carbon\Carbon::parse($request->end_date);
        $hours = null;

        // Count weekdays only
        $days    = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            if ($current->isWeekday()) $days++;
            $current->addDay();
        }

        // Override with time-based hours if single day + times provided
        if ($request->filled('start_time') && $request->filled('end_time')
            && $request->start_date === $request->end_date) {
            $tStart   = \Carbon\Carbon::createFromFormat('H:i', $request->start_time);
            $tEnd     = \Carbon\Carbon::createFromFormat('H:i', $request->end_time);

            $startMins = $tStart->hour * 60 + $tStart->minute;
            $endMins   = $tEnd->hour * 60 + $tEnd->minute;
            $totalMins = $endMins - $startMins;

            // Subtract overlap with lunch break 12:00–13:00
            $lunchStart  = 12 * 60;
            $lunchEnd    = 13 * 60;
            $overlapStart = max($startMins, $lunchStart);
            $overlapEnd   = min($endMins, $lunchEnd);
            if ($overlapEnd > $overlapStart) {
                $totalMins -= ($overlapEnd - $overlapStart);
            }

            $hours = round($totalMins / 60, 1);
            $days  = round($hours / 8, 2);
        }

        $status          = $request->action === 'submit' ? '簽核中' : '草稿';
        $currentApprover = null;
        // Balance validation — only when submitting
        if ($status === '簽核中') {
            $roleValue = $employee->role instanceof \App\Enums\Role
                ? $employee->role->value : $employee->role;

                $currentApprover = match($roleValue) {
                    '部門主管' => 'hr',      // manager's leave goes to HR
                    '人資部'   => 'hr',      // HR's leave also goes to HR (another HR member)
                    default   => 'manager', // regular employee goes to manager
                };

            // Check for overlapping leave requests (excluding drafts and rejected)
            $overlap = LeaveRequest::where('employee_id', $employee->id)
                ->whereNotIn('status', ['草稿', '已拒絕'])
                ->where(function ($query) use ($request) {
                    $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                        ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                        ->orWhere(function ($q) use ($request) {
                            $q->where('start_date', '<=', $request->start_date)
                                ->where('end_date', '>=', $request->end_date);
                        });
                })
                ->first();

            if ($overlap) {
                $overlapType = $overlap->leave_type instanceof \App\Enums\LeaveType
                    ? $overlap->leave_type->value
                    : $overlap->leave_type;
                return back()
                    ->withErrors([
                        'start_date' => "所選日期與現有假單重疊（{$overlapType}：{$overlap->start_date->format('Y/m/d')} ~ {$overlap->end_date->format('Y/m/d')}），請重新選擇日期。"
                    ])
                    ->withInput();
            }

            if ($request->leave_type === '特休假' && $days > $employee->remainingAnnualLeave()) {
                return back()->withErrors(['leave_type' => '特別休假餘額不足，您目前剩餘 '.$employee->remainingAnnualLeave().' 天。'])->withInput();
            }
            if ($request->leave_type === '病假' && ($employee->usedSickLeave() + $days) > 30) {
                return back()->withErrors(['leave_type' => '病假已超過年度上限30天。'])->withInput();
            }
            if ($request->leave_type === '事假' && ($employee->usedPersonalLeave() + $days) > 14) {
                return back()->withErrors(['leave_type' => '事假已超過年度上限14天。'])->withInput();
            }
            if ($request->leave_type === '生理假' && $employee->usedMenstrualLeaveThisMonth() >= 1) {
                return back()->withErrors(['leave_type' => '本月生理假已請畢（上限1天）。'])->withInput();
            }
            if ($request->leave_type === '補休' && ($days * 8) > $employee->compensatory_hours_remaining) {
                return back()->withErrors(['leave_type' => '補休時數不足，您目前剩餘 '.$employee->compensatory_hours_remaining.' 小時。'])->withInput();
            }
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id'      => $employee->id,
            'leave_type'       => $request->leave_type,
            'leave_reason'     => $request->leave_reason,
            'start_date'       => $request->start_date,
            'end_date'         => $request->end_date,
            'days'             => $days,
            'hours'            => $hours,
            'start_time'       => $request->start_time ?? null,
            'end_time'         => $request->end_time ?? null,
            'status'           => $status,
            'current_approver' => $currentApprover,
            'admin_note'       => null,
        ]);

        if ($status === '簽核中') {
            try {
                $mqtt = new MqttService();
                $mqtt->publish('leave/submitted', [
                    'leave_id'      => $leaveRequest->id,
                    'employee_id'   => $employee->id,
                    'employee_name' => $employee->name,
                    'employee_no'   => $employee->employee_no,
                    'department'    => $employee->department,
                    'leave_type'    => $request->leave_type,
                    'start_date'    => $request->start_date,
                    'end_date'      => $request->end_date,
                    'days'          => $days,
                    'timestamp'     => now()->toIso8601String(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('MQTT publish failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('leave.index')
            ->with('success', $status === '草稿' ? '草稿已儲存。' : '申請已送出，等待主管審核。');
    }

    public function show($id)
    {
        $employee = Auth::user();
        $leave    = LeaveRequest::findOrFail($id);
        return view('leave.show', compact('leave', 'employee'));
    }

    public function edit($id)
    {
        $employee   = Auth::user();
        $leave      = LeaveRequest::where('id', $id)
                        ->where('employee_id', $employee->id)
                        ->where('status', '草稿')
                        ->firstOrFail();
        $leaveTypes = LeaveType::cases();
        $balances   = [
            'annual_remaining'   => $employee->remainingAnnualLeave(),
            'sick_used'          => $employee->usedSickLeave(),
            'personal_used'      => $employee->usedPersonalLeave(),
            'compensatory_hours' => $employee->compensatory_hours_remaining,
        ];
        return view('leave.create', compact('employee', 'leave', 'leaveTypes', 'balances'));
    }

    public function update(Request $request, $id)
    {
        $employee = Auth::user();
        $leave    = LeaveRequest::where('id', $id)
                        ->where('employee_id', $employee->id)
                        ->where('status', '草稿')
                        ->firstOrFail();

        $request->validate([
            'leave_type'   => 'required',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'leave_reason' => 'required|string|max:500',
            'action'       => 'required|in:draft,submit',
            'start_time'   => 'nullable|date_format:H:i',
            'end_time'     => 'nullable|date_format:H:i',
        ]);

        $start = \Carbon\Carbon::parse($request->start_date);
        $end   = \Carbon\Carbon::parse($request->end_date);
        $hours = null;

        // Count weekdays only
        $days    = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            if ($current->isWeekday()) $days++;
            $current->addDay();
        }

        // Override with time-based hours if single day + times provided
        if ($request->filled('start_time') && $request->filled('end_time')
            && $request->start_date === $request->end_date) {
            $tStart   = \Carbon\Carbon::createFromFormat('H:i', $request->start_time);
            $tEnd     = \Carbon\Carbon::createFromFormat('H:i', $request->end_time);

            $startMins = $tStart->hour * 60 + $tStart->minute;
            $endMins   = $tEnd->hour * 60 + $tEnd->minute;
            $totalMins = $endMins - $startMins;

            // Subtract overlap with lunch break 12:00–13:00
            $lunchStart  = 12 * 60;
            $lunchEnd    = 13 * 60;
            $overlapStart = max($startMins, $lunchStart);
            $overlapEnd   = min($endMins, $lunchEnd);
            if ($overlapEnd > $overlapStart) {
                $totalMins -= ($overlapEnd - $overlapStart);
            }

            $hours = round($totalMins / 60, 1);
            $days  = round($hours / 8, 2);
        }

        $status = $request->action === 'submit' ? '簽核中' : '草稿';
        $currentApprover = null;
        if ($status === '簽核中') {
            $roleValue = $employee->role instanceof \App\Enums\Role
                ? $employee->role->value : $employee->role;

            $currentApprover = match($roleValue) {
                '部門主管' => 'hr',
                '人資部'   => 'hr',
                default    => 'manager',
            };
        }

        // Balance validation — only when submitting
        if ($status === '簽核中') {

            if ($start->isToday() && now()->hour >= 12) {
                return back()
                    ->withErrors([
                        'start_date' => '當日請假須於中午12:00前提出申請。'
                    ])
                    ->withInput();
            }

            // Overlap check (exclude current draft being submitted)
            $overlap = LeaveRequest::where('employee_id', $employee->id)
                ->where('id', '!=', $id)
                ->whereNotIn('status', ['草稿', '已拒絕'])
                ->where(function ($query) use ($request) {
                    $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                        ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                        ->orWhere(function ($q) use ($request) {
                            $q->where('start_date', '<=', $request->start_date)
                                ->where('end_date', '>=', $request->end_date);
                        });
                })
                ->first();

            if ($overlap) {
                $overlapType = $overlap->leave_type instanceof \App\Enums\LeaveType
                    ? $overlap->leave_type->value
                    : $overlap->leave_type;
                return back()
                    ->withErrors([
                        'start_date' => "所選日期與現有假單重疊（{$overlapType}：{$overlap->start_date->format('Y/m/d')} ~ {$overlap->end_date->format('Y/m/d')}），請重新選擇日期。"
                    ])
                    ->withInput();
            }

            if ($request->leave_type === '特休假' && $days > $employee->remainingAnnualLeave()) {
                return back()->withErrors(['leave_type' => '特別休假餘額不足，您目前剩餘 '.$employee->remainingAnnualLeave().' 天。'])->withInput();
            }
            if ($request->leave_type === '病假' && ($employee->usedSickLeave() + $days) > 30) {
                return back()->withErrors(['leave_type' => '病假已超過年度上限30天。'])->withInput();
            }
            if ($request->leave_type === '事假' && ($employee->usedPersonalLeave() + $days) > 14) {
                return back()->withErrors(['leave_type' => '事假已超過年度上限14天。'])->withInput();
            }
            if ($request->leave_type === '生理假' && $employee->usedMenstrualLeaveThisMonth() >= 1) {
                return back()->withErrors(['leave_type' => '本月生理假已請畢（上限1天）。'])->withInput();
            }
            if ($request->leave_type === '補休' && ($days * 8) > $employee->compensatory_hours_remaining) {
                return back()->withErrors(['leave_type' => '補休時數不足，您目前剩餘 '.$employee->compensatory_hours_remaining.' 小時。'])->withInput();
            }
        }

        $leave->update([
            'leave_type'       => $request->leave_type,
            'leave_reason'     => $request->leave_reason,
            'start_date'       => $request->start_date,
            'end_date'         => $request->end_date,
            'days'             => $days,
            'hours'            => $hours,
            'start_time'       => $request->start_time ?? null,
            'end_time'         => $request->end_time ?? null,
            'status'           => $status,
            'current_approver' => $currentApprover,
        ]);

        return redirect()->route('leave.show', $id)
            ->with('success', $status === '草稿' ? '草稿已更新。' : '申請已送出。');
    }

    public function destroy($id)
    {
        $employee = Auth::user();
        LeaveRequest::where('id', $id)
            ->where('employee_id', $employee->id)
            ->where('status', '草稿')
            ->firstOrFail()
            ->delete();

        return redirect()->route('leave.index')->with('success', '草稿已刪除。');
    }
}
