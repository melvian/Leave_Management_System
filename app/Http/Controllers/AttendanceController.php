<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\Employee;
use Carbon\Carbon;  
use Maatwebsite\Excel\Facades\Excel;
use App\Services\MqttService;

class AttendanceController extends Controller
{
    const SHIFT_START    = '09:00';
    const SHIFT_END      = '18:00';
    const LATE_TOLERANCE = 11; // minutes
    const LUNCH_START    = '12:00';
    const LUNCH_END      = '13:00';

    public function index()
    {
        $employee = Auth::user();
        $today    = now()->toDateString();

        $todayRecord = AttendanceRecord::whereDate('date', $today)
            ->where('employee_id', $employee->id)
            ->first();

        // Check if today has approved leave
        $hasLeaveToday = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', '已核准')
            ->where('start_date', '<=', $today)
            ->where('end_date',   '>=', $today)
            ->exists();

        // Last 30 days history
        $history = AttendanceRecord::where('employee_id', $employee->id)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        // Stats for current month
        $monthStats = [
            'normal'      => AttendanceRecord::where('employee_id', $employee->id)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->where('status', 'normal')->count(),
            'late'        => AttendanceRecord::where('employee_id', $employee->id)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->where('status', 'late')->count(),
            'early_leave' => AttendanceRecord::where('employee_id', $employee->id)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->where('status', 'early_leave')->count(),
            'absent'      => AttendanceRecord::where('employee_id', $employee->id)
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->where('status', 'absent')->count(),
        ];

        return view('attendance.index', compact(
            'employee', 'todayRecord', 'history', 'hasLeaveToday', 'monthStats'
        ));
    }

    public function clockIn(Request $request)
    {
        $employee = Auth::user();
        $now      = now();
        $today    = $now->toDateString();

        // Get shift settings for this employee's department
        $shift = \App\Models\ShiftSetting::forDepartment($employee->department);

        // Cannot clock in on weekends
        if ($now->isWeekend()) {
            return back()->with('error', '假日無需打卡。');
        }

        // Cannot clock in on holidays
        $isHoliday = \App\Models\Holiday::whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)->exists();

        if ($isHoliday) {
            $holidayName = \App\Models\Holiday::whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)->value('name');
            return back()->with('error', "今日為假日（{$holidayName}），無需打卡。");
        }

        // Cannot clock in during approved leave
        $fullDayLeave = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', '已核准')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->whereNull('hours') // full day only
            ->exists();

        if ($fullDayLeave) {
            return back()->with('error', '今日已請全天假，無法打卡。');
        }

        // ── Calculate late minutes using dept shift ──────────
        $shiftStart  = Carbon::parse($today . ' ' . $shift->shift_start);
        $lateMinutes = 0;
        $status      = 'normal';

        if ($now->gt($shiftStart->copy()->addMinutes($shift->late_tolerance))) {
            $lateMinutes = (int) $shiftStart->diffInMinutes($now);
            $status      = 'late';
        }

        // Force find using whereDate to handle SQLite date storage quirk
        $existing = AttendanceRecord::whereDate('date', $today)
            ->where('employee_id', $employee->id)
            ->first();

        if ($existing) {
            if ($existing->clock_in) {
                return redirect()->route('attendance.index')
                    ->with('error', '今日已打卡上班。');
            }
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
                'timestamp'     => $now->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('MQTT publish failed: ' . $e->getMessage());
        }

        $message = $lateMinutes > 0
            ? "上班打卡成功。今日遲到 {$lateMinutes} 分鐘。（班次：{$shift->shift_start}–{$shift->shift_end}）"
            : '上班打卡成功，準時上班！';

        return redirect()->route('attendance.index')
            ->with('success', $message);
    }

    public function clockOut(Request $request)
    {
        $employee = Auth::user();
        $now      = now();
        $today    = $now->toDateString();

        $shift=\App\Models\ShiftSetting::forDepartment($employee->department);

        $record = AttendanceRecord::whereDate('date', $today)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$record || !$record->clock_in) {
            return redirect()->route('attendance.index')
                ->with('error', '請先打卡上班。');
        }

        if ($record->clock_out) {
            return redirect()->route('attendance.index')
                ->with('error', '今日已打卡下班。');
        }

        // Calculate worked hours (exclude lunch 12:00–13:00)
        $clockIn   = Carbon::parse($record->clock_in)->setTimezone(config('app.timezone'));
        $totalMins = $clockIn->diffInMinutes($now);

        // Subtract lunch overlap
        $lunchStart   = Carbon::parse($today . ' 12:00' );
        $lunchEnd     = Carbon::parse($today . ' 13:00' );
        $overlapStart = max($clockIn->timestamp, $lunchStart->timestamp);
        $overlapEnd   = min($now->timestamp, $lunchEnd->timestamp);
        if ($overlapEnd > $overlapStart) {
            $totalMins -= ($overlapEnd - $overlapStart) / 60;
        }

        $workedHours = round($totalMins / 60, 2);
        // Check early leave
        $shiftEnd          = Carbon::parse($today . ' ' . $shift->shift_end);
        $earlyLeaveMinutes = 0;
        $status            = $record->status;

        if ($now->lt($shiftEnd)) {
            $earlyLeaveMinutes = (int) $now->diffInMinutes($shiftEnd);
            // if ($status === 'normal') $status = 'early_leave';
            // elseif ($status === 'late') $status = 'late';

            // Check if early leave covered by approved hourly leave
            $hourlyLeave = LeaveRequest::where('employee_id', $employee->id)
                ->where('status', '已核准')
                ->whereDate('start_date', $today)
                ->whereNotNull('hours')
                ->first();

            if ($hourlyLeave && $hourlyLeave->start_time) {
                $leaveStartTime = Carbon::parse($today . ' ' . $hourlyLeave->start_time);
                if ($now->gte($leaveStartTime->copy()->subMinutes($shift->late_tolerance))) {
                    $earlyLeaveMinutes = 0;
                }
            }

            if ($earlyLeaveMinutes > 0 && $status === 'normal') {
                $status = 'early_leave';
            }
        }

        // NOW update with correct values
        $record->update([
            'clock_out'           => $now,
            'worked_hours'        => $workedHours,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'status'              => $status,
        ]);

        // After $record->update([...])
        try {
            $mqtt = new MqttService();
            $mqtt->publish('attendance/clock-out', [
                'employee_id'          => $employee->id,
                'employee_name'        => $employee->name,
                'employee_no'          => $employee->employee_no,
                'department'           => $employee->department,
                'clock_out'            => $now->toIso8601String(),
                'worked_hours'         => $workedHours,
                'early_leave_minutes'  => $earlyLeaveMinutes,
                'status'               => $status,
                'timestamp'            => $now->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('MQTT publish failed: ' . $e->getMessage());
        }

        $message = "下班打卡成功。今日實際工時：{$workedHours} 小時。";
        if ($earlyLeaveMinutes > 0) {
            $message .= " 您提早 {$earlyLeaveMinutes} 分鐘離開。";
        }

        // Check if worked past shift end → redirect to overtime form
        if ($now->gt($shiftEnd)) {
            $overtimeHours = round($now->diffInMinutes($shiftEnd) / 60, 1);
            return redirect()->route('overtime.create', [
                'prefill_date'  => $today,
                'prefill_start' => $shiftEnd->format('H:i'),
                'prefill_end'   => $now->format('H:i'),
                'prefill_hours' => $overtimeHours,
            ])->with('info', "下班打卡成功（工時：{$workedHours} 小時）。系統偵測到您超時工作 {$overtimeHours} 小時，如為工作需要請填寫加班申請。");
        }

        return redirect()->route('attendance.index')
            ->with('success', $message);
    }

    public function management(Request $request)
    {
        $date         = $request->get('date', now()->toDateString());
        $selectedDept = $request->get('dept', '');
        $filter       = $request->get('filter', ''); // normal/late/early_leave/on_leave/absent
        $departments  = \App\Models\Employee::distinct()->pluck('department')->sort()->values();

        $query = AttendanceRecord::with('employee')->whereDate('date', $date);
        if ($selectedDept) {
            $query->whereHas('employee', fn($q) =>
                $q->where('department', $selectedDept));
        }
        $records = $query->orderBy('clock_in')->get();

        // All active employees filtered by dept
        $empQuery = \App\Models\Employee::where('is_active', true);
        if ($selectedDept) $empQuery->where('department', $selectedDept);
        $allEmployees = $empQuery->get();
        $clockedInIds = $records->pluck('employee_id')->toArray();

        // Employees on approved leave today
        $onLeaveEmployees = $allEmployees->filter(function ($emp) use ($date) {
            return LeaveRequest::where('employee_id', $emp->id)
                ->where('status', '已核准')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->exists();
        });

        // Employees absent without leave
        $absentWithoutLeave = $allEmployees->filter(function ($emp)
            use ($clockedInIds, $date) {
            if (in_array($emp->id, $clockedInIds)) return false;
            $hasLeave = LeaveRequest::where('employee_id', $emp->id)
                ->where('status', '已核准')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->exists();

            $isHoliday = \App\Models\Holiday::whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->exists();
            return !$hasLeave && !$isHoliday;
        });

        // Summary counts
        $summary = [
            'normal'      => $records->where('status', 'normal')->count(),
            'late'        => $records->where('status', 'late')->count(),
            'early_leave' => $records->where('early_leave_minutes', '>', 0)->count(),
            'on_leave'    => $onLeaveEmployees->count(),
            'absent'      => $absentWithoutLeave->count(),
        ];

        // Apply filter to records shown
        $filteredRecords = match($filter) {
            'normal'      => $records->where('status', 'normal'),
            'late'        => $records->where('status', 'late'),
            'early_leave' => $records->filter(fn($r) => $r->early_leave_minutes > 0),
            default       => $records,
        };

        return view('attendance.management', compact(
            'records', 'filteredRecords', 'date', 'selectedDept', 'filter',
            'departments', 'summary', 'absentWithoutLeave',
            'onLeaveEmployees'
        ));
    }

    // Feature E — Export CSV
public function export(Request $request)
{
    $date         = $request->get('date', now()->toDateString());
    $selectedDept = $request->get('dept', '');

    $query = AttendanceRecord::with('employee')->whereDate('date', $date);
    if ($selectedDept) {
        $query->whereHas('employee', fn($q) =>
            $q->where('department', $selectedDept));
    }
    $records = $query->orderBy('clock_in')->get();

    $empQuery = Employee::where('is_active', true);
    if ($selectedDept) $empQuery->where('department', $selectedDept);
    $allEmployees = $empQuery->get();
    $clockedInIds = $records->pluck('employee_id')->toArray();

    $onLeaveEmployees = $allEmployees->filter(function ($emp) use ($date) {
        return LeaveRequest::where('employee_id', $emp->id)
            ->where('status', '已核准')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    });

    $absentWithoutLeave = $allEmployees->filter(function ($emp) use ($clockedInIds, $date) {
        if (in_array($emp->id, $clockedInIds)) return false;
        return !LeaveRequest::where('employee_id', $emp->id)
            ->where('status', '已核准')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    });

    $filename = "出勤記錄_{$date}" . ($selectedDept ? "_{$selectedDept}" : '') . ".xlsx";

    return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\AttendanceDailyExport(
            $records,
            $onLeaveEmployees,
            $absentWithoutLeave,
            $date
        ),
        $filename
    );
}

// Helper to build attendance row array
private function attendanceRow(AttendanceRecord $r, string $date): array
{
    return [
        $r->employee->employee_no,
        $r->employee->name,
        $r->employee->department,
        $r->clock_in  ? \Carbon\Carbon::parse($r->clock_in)->format('H:i')  : '',
        $r->clock_out ? \Carbon\Carbon::parse($r->clock_out)->format('H:i') : '',
        $r->worked_hours ?? '',
        $r->late_minutes ?? 0,
        $r->early_leave_minutes ?? 0,
        $r->statusLabel(),
    ];
}

// Feature F — Manual correction
public function correct(Request $request)
    {
        $request->validate([
            'record_id' => 'required|exists:attendance_records,id',
            'clock_in'  => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
        ]);

        $record = AttendanceRecord::findOrFail($request->record_id);
        $date   = $record->date->toDateString();

        $clockIn  = $request->clock_in
            ? \Carbon\Carbon::parse($date . ' ' . $request->clock_in)
            : null;
        $clockOut = $request->clock_out
            ? \Carbon\Carbon::parse($date . ' ' . $request->clock_out)
            : null;

        // Recalculate worked hours if both times present
        $workedHours        = null;
        $lateMinutes        = $record->late_minutes;
        $earlyLeaveMinutes  = $record->early_leave_minutes;
        $status             = $record->status;

        if ($clockIn && $clockOut) {
            $totalMins = $clockIn->diffInMinutes($clockOut);

            // Subtract lunch overlap
            $lunchStart   = \Carbon\Carbon::parse($date . ' 12:00');
            $lunchEnd     = \Carbon\Carbon::parse($date . ' 13:00');
            $overlapStart = max($clockIn->timestamp, $lunchStart->timestamp);
            $overlapEnd   = min($clockOut->timestamp, $lunchEnd->timestamp);
            if ($overlapEnd > $overlapStart) {
                $totalMins -= ($overlapEnd - $overlapStart) / 60;
            }

            $workedHours = round($totalMins / 60, 2);

            // Recalculate late minutes
            $shiftStart  = \Carbon\Carbon::parse($date . ' 09:00');
            $tolerance   = $shiftStart->copy()->addMinutes(10);
            if ($clockIn->gt($tolerance)) {
                $lateMinutes = (int) $shiftStart->diffInMinutes($clockIn);
                $status      = 'late';
            } else {
                $lateMinutes = 0;
                $status      = 'normal';
            }

            // Recalculate early leave
            $shiftEnd = \Carbon\Carbon::parse($date . ' 18:00');
            if ($clockOut->lt($shiftEnd)) {
                $earlyLeaveMinutes = (int) $clockOut->diffInMinutes($shiftEnd);
                if ($status === 'normal') $status = 'early_leave';
            } else {
                $earlyLeaveMinutes = 0;
            }
        }

        $record->update([
            'clock_in'            => $clockIn,
            'clock_out'           => $clockOut,
            'worked_hours'        => $workedHours,
            'late_minutes'        => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'status'              => $status,
        ]);

        return redirect()->route('attendance.management.index', [
            'date' => $request->date,
            'dept' => $request->dept,
        ])->with('success', '打卡記錄已修正。');
    }
}
