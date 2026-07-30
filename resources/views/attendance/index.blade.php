@extends('layouts.app')
@section('page-title', '打卡記錄')

@section('content')

{{-- Today's clock card --}}
@php
    $myShift = \App\Models\ShiftSetting::forDepartment(Auth::user()->department);
@endphp
<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-fingerprint me-2"></i>今日打卡
                    <span class="ms-2 text-white-50" style="font-size:12px;">
                        {{ now()->format('Y/m/d') }}（{{ ['日','一','二','三','四','五','六'][now()->dayOfWeek] }}）
                    </span>
                </span>
            </div>
            <div class="card-body">

                @if($hasLeaveToday)
                <div class="alert alert-info d-flex align-items-center mb-3">
                    <i class="bi bi-calendar-check me-2"></i>
                    您今日有核准的請假，無需打卡。
                </div>
                @endif

                {{-- Clock in/out display --}}
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="text-center p-3 rounded"
                            style="background:{{ $todayRecord?->clock_in ? '#d1e7dd' : '#f8f9fa' }};">
                            <div style="font-size:12px; color:#888; margin-bottom:4px;">上班打卡</div>
                            <div style="font-size:22px; font-weight:700;
                                color:{{ $todayRecord?->clock_in ? '#0f5132' : '#ccc' }};">
                                {{ $todayRecord?->clock_in
                                    ? \Carbon\Carbon::parse($todayRecord->clock_in)->format('H:i')
                                    : '--:--' }}
                            </div>
                            @if($todayRecord?->late_minutes > 0)
                            <div style="font-size:11px; color:#856404;">
                                遲到 {{ $todayRecord->late_minutes }} 分鐘
                            </div>
                            @elseif($todayRecord?->clock_in)
                            <div style="font-size:11px; color:#0f5132;">準時</div>
                            @endif
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-3 rounded"
                            style="background:{{ $todayRecord?->clock_out ? '#d1e7dd' : '#f8f9fa' }};">
                            <div style="font-size:12px; color:#888; margin-bottom:4px;">下班打卡</div>
                            <div style="font-size:22px; font-weight:700;
                                color:{{ $todayRecord?->clock_out ? '#0f5132' : '#ccc' }};">
                                {{ $todayRecord?->clock_out
                                    ? \Carbon\Carbon::parse($todayRecord->clock_out)->format('H:i')
                                    : '--:--' }}
                            </div>
                            @if($todayRecord?->early_leave_minutes > 0)
                            <div style="font-size:11px; color:#856404;">
                                早退 {{ $todayRecord->early_leave_minutes }} 分鐘
                            </div>
                            @elseif($todayRecord?->clock_out)
                            <div style="font-size:11px; color:#0f5132;">
                                工時 {{ $todayRecord->worked_hours }} 小時
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Action buttons --}}
                <div class="d-flex gap-3">
                    @if(!$todayRecord || !$todayRecord->clock_in)
                        <form method="POST" action="{{ route('attendance.clock-in') }}"
                            class="flex-fill">
                            @csrf
                            <button type="submit" class="btn btn-success w-100 py-3"
                                onclick="return confirm('確認上班打卡？\n時間：' + new Date().toLocaleTimeString('zh-TW'))">
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                <span class="fw-bold">上班打卡</span>
                            </button>
                        </form>
                    @else
                        <button class="btn flex-fill py-3" disabled
                            style="background:#d1e7dd; color:#0f5132; border:none;">
                            <i class="bi bi-check-circle me-2"></i>已完成上班打卡
                        </button>
                    @endif

                    @if($todayRecord?->clock_in && !$todayRecord?->clock_out)
                        <form method="POST" action="{{ route('attendance.clock-out') }}"
                            class="flex-fill">
                            @csrf
                            <button type="submit" class="btn btn-warning w-100 py-3 text-dark"
                                onclick="return confirm('確認下班打卡？\n時間：' + new Date().toLocaleTimeString('zh-TW'))">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                <span class="fw-bold">下班打卡</span>
                            </button>
                        </form>
                    @elseif($todayRecord?->clock_out)
                        <button class="btn flex-fill py-3" disabled
                            style="background:#d1e7dd; color:#0f5132; border:none;">
                            <i class="bi bi-check-circle me-2"></i>已完成下班打卡
                        </button>
                    @else
                        <button class="btn btn-secondary flex-fill py-3" disabled>
                            <i class="bi bi-box-arrow-right me-2"></i>下班打卡
                        </button>
                    @endif
                </div>

                <div class="text-center mt-3 text-muted" style="font-size:12px;">
                    上班時間 {{ $myShift->shift_start }}｜下班時間 {{ $myShift->shift_end }}｜遲到容忍 {{ $myShift->late_tolerance }} 分鐘
                </div>
            </div>
        </div>
    </div>

    {{-- Monthly stats --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-bar-chart me-2"></i>本月出勤統計
                    （{{ now()->format('Y年m月') }}）
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="stat-card" style="border-left-color:#198754;">
                            <div class="stat-label">正常出勤</div>
                            <div class="stat-value" style="color:#198754;">{{ $monthStats['normal'] }}</div>
                            <div class="stat-sub">天</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card" style="border-left-color:#ffc107;">
                            <div class="stat-label">遲到</div>
                            <div class="stat-value" style="color:#856404;">{{ $monthStats['late'] }}</div>
                            <div class="stat-sub">天</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card" style="border-left-color:#fd7e14;">
                            <div class="stat-label">早退</div>
                            <div class="stat-value" style="color:#842029;">{{ $monthStats['early_leave'] }}</div>
                            <div class="stat-sub">天</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card" style="border-left-color:#dc3545;">
                            <div class="stat-label">曠職</div>
                            <div class="stat-value" style="color:#dc3545;">{{ $monthStats['absent'] }}</div>
                            <div class="stat-sub">天</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Monthly calendar - scrollable 3 months --}}
<div class="card mb-4">
    <div class="card-header card-header-navy py-2 px-4 d-flex justify-content-between align-items-center">
        <span style="font-size:14px;font-weight:600;">
            <i class="bi bi-calendar3 me-2"></i>出勤日曆
        </span>
        <div style="font-size:12px;" class="d-flex gap-3 text-white-50">
            <span><i class="bi bi-circle-fill me-1" style="color:#198754;"></i>正常</span>
            <span><i class="bi bi-circle-fill me-1" style="color:#ffc107;"></i>遲到/早退</span>
            <span><i class="bi bi-circle-fill me-1" style="color:#dc3545;"></i>曠職</span>
            <span><i class="bi bi-circle-fill me-1" style="color:#0E7C86;"></i>請假</span>
            <span><i class="bi bi-circle-fill me-1" style="color:#6A1B9A;"></i>假日</span>
            <span><i class="bi bi-circle-fill me-1" style="color:#dee2e6;"></i>週末</span>
        </div>
    </div>

    {{-- Scrollable container --}}
    <div style="height:520px; overflow-y:scroll; scroll-snap-type:y mandatory;"
        id="calendarScroll">

        @php
            $renderMonths = [
                now()->subMonth(),
                now(),
                now()->addMonth(),
            ];

            // Fetch attendance for ±1 month range
            $rangeStart = now()->subMonth()->startOfMonth()->toDateString();
            $rangeEnd   = now()->addMonth()->endOfMonth()->toDateString();

            $allRecords = \App\Models\AttendanceRecord::where('employee_id', $employee->id)
                ->whereDate('date', '>=', $rangeStart)
                ->whereDate('date', '<=', $rangeEnd)
                ->get();

            $allLeaves = \App\Models\LeaveRequest::where('employee_id', $employee->id)
                ->where('status', '已核准')
                ->where('start_date', '<=', $rangeEnd)
                ->where('end_date', '>=', $rangeStart)
                ->get();

            $allHolidays = \App\Models\Holiday::where('start_date', '<=', $rangeEnd)
                ->where('end_date', '>=', $rangeStart)
                ->get();
        @endphp

        @foreach($renderMonths as $monthDate)
        @php
            $mYear       = $monthDate->year;
            $mMonth      = $monthDate->month;
            $daysInMonth = $monthDate->daysInMonth;
            $firstDOW    = \Carbon\Carbon::create($mYear, $mMonth, 1)->dayOfWeek;
            $isCurrentMonth = $mYear === now()->year && $mMonth === now()->month;

            // Filter records for this month
            $monthRecords = $allRecords->filter(fn($r) =>
                \Carbon\Carbon::parse($r->date)->year === $mYear &&
                \Carbon\Carbon::parse($r->date)->month === $mMonth
            )->keyBy(fn($r) => \Carbon\Carbon::parse($r->date)->day);

            // Build leave days for this month
            $leaveDays = [];
            foreach ($allLeaves as $leave) {
                $ls = \Carbon\Carbon::parse($leave->start_date);
                $le = \Carbon\Carbon::parse($leave->end_date);
                $lc = $ls->copy();
                while ($lc->lte($le)) {
                    if ($lc->year === $mYear && $lc->month === $mMonth) {
                        $leaveDays[$lc->day] = $leave;
                    }
                    $lc->addDay();
                }
            }

            // Build holiday days for this month
            $holidayDays = [];
            foreach ($allHolidays as $hol) {
                $hs = \Carbon\Carbon::parse($hol->start_date);
                $he = \Carbon\Carbon::parse($hol->end_date);
                $hc = $hs->copy();
                while ($hc->lte($he)) {
                    if ($hc->year === $mYear && $hc->month === $mMonth) {
                        $holidayDays[$hc->day] = $hol;
                    }
                    $hc->addDay();
                }
            }
        @endphp

        {{-- One month block --}}
        <div style="height:520px; scroll-snap-align:start; padding:20px 24px;
                    border-bottom:2px solid #e0e6ef; flex-shrink:0;">

            {{-- Month title --}}
            <div class="d-flex align-items-center mb-3">
                <h5 class="fw-bold mb-0" style="color:#1F3864;">
                    {{ $mYear }}年{{ $mMonth }}月
                </h5>
                @if($isCurrentMonth)
                <span class="badge bg-primary ms-2" style="font-size:11px;">本月</span>
                @endif
            </div>

            {{-- Day headers --}}
            <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:5px; margin-bottom:5px;">
                @foreach(['日','一','二','三','四','五','六'] as $i => $dayName)
                <div class="text-center fw-semibold"
                    style="font-size:11px; color:{{ $i===0||$i===6 ? '#aaa' : '#555' }}; padding:4px 0;">
                    {{ $dayName }}
                </div>
                @endforeach
            </div>

            {{-- Day grid --}}
            <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:5px;">

                {{-- Empty cells --}}
                @for($i = 0; $i < $firstDOW; $i++)
                <div></div>
                @endfor

                {{-- Day cells --}}
                @for($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $cellDate  = \Carbon\Carbon::create($mYear, $mMonth, $day);
                    $isToday   = $cellDate->isToday();
                    $isWeekend = $cellDate->isWeekend();
                    $isFuture  = $cellDate->isFuture() && !$isToday;
                    $record    = $monthRecords[$day] ?? null;
                    $onLeave   = $leaveDays[$day] ?? null;
                    $holiday   = $holidayDays[$day] ?? null;

                    if ($holiday) {
                        $bg    = '#EDE7F6';
                        $color = '#6A1B9A';
                        $label = $holiday->name;
                    } elseif ($isWeekend) {
                        $bg    = '#f8f9fa';
                        $color = '#aaa';
                        $label = '';
                    } elseif ($isFuture) {
                        $bg    = '#fff';
                        $color = '#ccc';
                        $label = '';
                    } elseif ($onLeave && !$record) {
                        $bg    = '#dae9f7';
                        $color = '#4596de';
                        $label = '請假';
                    } elseif ($record) {
                        $status = $record->status;
                        if ($status === 'normal') {
                            $bg = '#d1e7dd'; $color = '#0f5132'; $label = '正常';
                        } elseif (in_array($status, ['late','early_leave'])) {
                            $bg = '#fff3cd'; $color = '#856404';
                            $label = $status === 'late' ? '遲到' : '早退';
                        } elseif ($status === 'absent') {
                            $bg = '#f8d7da'; $color = '#842029'; $label = '曠職';
                        } elseif ($status === 'on_leave') {
                            $bg = '#dae9f7'; $color = '#4596de'; $label = '請假';
                        } else {
                            $bg = '#fff'; $color = '#333'; $label = '';
                        }
                    } else {
                        $bg    = '#f8d7da';
                        $color = '#842029';
                        $label = '未打卡';
                    }
                @endphp
                <div class="text-center rounded py-1"
                    style="background:{{ $bg }};
                           border:{{ $isToday ? '2px solid #1F3864' : '1px solid #eee' }};
                           min-height:58px;">
                    <div style="font-size:14px; font-weight:{{ $isToday ? '700' : '500' }};
                                color:{{ $isWeekend && !$holiday ? '#aaa' : ($isFuture ? '#ccc' : $color) }};">
                        {{ $day }}
                    </div>
                    @if($label)
                    <div style="font-size:12px; color:{{ $color }}; margin-top:1px; line-height:1.2; padding:0 2px;">
                        {{ $label }}
                    </div>
                    @endif
                    @if($record?->clock_in && !$isWeekend && !$holiday)
                    <div style="font-size:10px; color:#888; margin-top:1px;">
                        {{ \Carbon\Carbon::parse($record->clock_in)->format('H:i') }}
                    </div>
                    @endif
                </div>
                @endfor

            </div>
        </div>
        @endforeach

    </div>

    {{-- Scroll indicator --}}
    <div class="text-center py-2 text-muted" style="font-size:12px; border-top:1px solid #eee;">
        <i class="bi bi-arrow-up me-1"></i>上個月
        <span class="mx-3">｜</span>
        捲動查看不同月份
        <span class="mx-3">｜</span>
        下個月<i class="bi bi-arrow-down ms-1"></i>
    </div>
</div>

{{-- History table --}}
<div class="card">
    <div class="card-header card-header-navy py-2 px-4">
        <span style="font-size:14px;font-weight:600;">
            <i class="bi bi-clock-history me-2"></i>近30日打卡記錄
        </span>
    </div>
    <div class="card-body p-0">
        @if($history->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-clock fs-1 d-block mb-2"></i>
                尚無打卡記錄
            </div>
        @else
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>日期</th>
                    <th>上班打卡</th>
                    <th>下班打卡</th>
                    <th>實際工時</th>
                    <th>遲到</th>
                    <th>早退</th>
                    <th>狀態</th>
                </tr>
            </thead>
            <tbody>
                @foreach($history as $record)
                <tr>
                    <td class="fw-semibold">
                        {{ $record->date->format('Y/m/d') }}
                        <span class="text-muted" style="font-size:12px;">
                            （{{ ['日','一','二','三','四','五','六'][$record->date->dayOfWeek] }}）
                        </span>
                    </td>
                    <td>
                        {{ $record->clock_in
                            ? \Carbon\Carbon::parse($record->clock_in)->format('H:i')
                            : '—' }}
                    </td>
                    <td>
                        {{ $record->clock_out
                            ? \Carbon\Carbon::parse($record->clock_out)->format('H:i')
                            : '—' }}
                    </td>
                    <td>
                        {{ $record->worked_hours ? $record->worked_hours.' 小時' : '—' }}
                    </td>
                    <td>
                        @if($record->late_minutes > 0)
                            <span class="text-warning fw-semibold">
                                {{ $record->late_minutes }} 分鐘
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($record->early_leave_minutes > 0)
                            <span class="text-warning fw-semibold">
                                {{ $record->early_leave_minutes }} 分鐘
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $record->statusColor() }}">
                            {{ $record->statusLabel() }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cal = document.getElementById('calendarScroll');
    if (cal) {
        // Get the second month block (index 1 = current month)
        const blocks = cal.children;
        if (blocks.length >= 2) {
            cal.scrollTop = blocks[1].offsetTop;
        }
    }
});
</script>
@endsection

@endsection