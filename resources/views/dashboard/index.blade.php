@extends('layouts.app')
@section('page-title', '儀表板')

@section('content')
@php
    $navRole = Auth::user()->role instanceof \App\Enums\Role
        ? Auth::user()->role->value : Auth::user()->role;
@endphp

{{-- Balance cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card" style="border-left-color:#2E74B5;">
            <div class="stat-label">特別休假</div>
            <div class="stat-value" style="color:{{ $balances['annual_remaining'] <= 0 ? '#dc3545' : '#1F3864' }};">
                {{ $balances['annual_remaining'] }}
            </div>
            <div class="stat-sub">剩餘天數（年度應得 {{ $balances['annual_entitlement'] }} 天）</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card" style="border-left-color:#E36C09;">
            <div class="stat-label">病假已請</div>
            <div class="stat-value">{{ $balances['sick_used'] }}</div>
            <div class="stat-sub">天（年度上限 30 天）</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card" style="border-left-color:#888;">
            <div class="stat-label">事假已請</div>
            <div class="stat-value">{{ $balances['personal_used'] }}</div>
            <div class="stat-sub">天（年度上限 14 天）</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card" style="border-left-color:#0E7C86;">
            <div class="stat-label">補休餘額</div>
            <div class="stat-value" style="color:#0E7C86;">{{ $balances['compensatory_hours'] }}</div>
            <div class="stat-sub">小時</div>
        </div>
    </div>
    @if($employee->gender === 'female')
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card" style="border-left-color:#C2486A;">
            <div class="stat-label">生理假（本月）</div>
            <div class="stat-value" style="color:#C2486A;">{{ $balances['menstrual_used'] ?? 0 }}</div>
            <div class="stat-sub">天（上限 1 天）</div>
        </div>
    </div>
    @endif
</div>

{{-- Pending approval alert --}}
@if($pendingCount > 0 && in_array($navRole, ['部門主管','人資部','系統管理者']))
<div class="info-box mb-4 d-flex align-items-center justify-content-between">
    <div>
        <i class="bi bi-bell-fill me-2" style="color:#2E74B5;"></i>
        您有 <strong>{{ $pendingCount }}</strong> 筆申請待審核
    </div>
    <a href="{{ route('approval.leave.index') }}" class="btn btn-sm btn-navy">
        前往審核 <i class="bi bi-arrow-right"></i>
    </a>
</div>
@endif

<div class="row g-3">

    {{-- Quick actions --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3" style="color:#1F3864;">
                    <i class="bi bi-lightning-charge me-2"></i>快速操作
                </h6>

                {{-- 打卡 section --}}
                @php
                    $todayAttendance = \App\Models\AttendanceRecord::whereDate('date', now()->toDateString())
                        ->where('employee_id', Auth::user()->id)
                        ->first();

                    $hasLeaveToday = \App\Models\LeaveRequest::where('employee_id', Auth::user()->id)
                        ->where('status', '已核准')
                        ->whereDate('start_date', '<=', now()->toDateString())
                        ->whereDate('end_date', '>=', now()->toDateString())
                        ->whereNull('hours')
                        ->exists();

                    $shouldRemind = !$todayAttendance
                        && !$hasLeaveToday
                        && now()->isWeekday()
                        && now()->hour >= 10;
                @endphp

                {{-- Absent reminder --}}
                @if($shouldRemind)
                <div class="alert alert-warning d-flex align-items-start mb-3 py-2">
                    <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                    <div>
                        <div class="fw-semibold" style="font-size:13px;">您今日尚未打卡上班</div>
                        <div style="font-size:12px;">已超過10:00，請確認是否需要
                            <a href="{{ route('leave.create') }}" class="alert-link">申請請假</a>
                        </div>
                    </div>
                </div>
                @endif

                <div class="p-3 rounded mb-3"
                    style="background:#f0f4f8; border-left:4px solid #1F3864;">
                    <div class="fw-semibold mb-2" style="font-size:13px; color:#1F3864;">
                        <i class="bi bi-fingerprint me-1"></i>今日打卡
                    </div>

                    <div class="d-flex justify-content-between mb-2"
                        style="font-size:12px; color:#888;">
                        <span>
                            上班：
                            <strong style="color:#333;">
                                {{ $todayAttendance?->clock_in
                                    ? \Carbon\Carbon::parse($todayAttendance->clock_in)->format('H:i')
                                    : '未打卡' }}
                            </strong>
                        </span>
                        <span>
                            下班：
                            <strong style="color:#333;">
                                {{ $todayAttendance?->clock_out
                                    ? \Carbon\Carbon::parse($todayAttendance->clock_out)->format('H:i')
                                    : '未打卡' }}
                            </strong>
                        </span>
                    </div>

                    <div class="d-flex gap-2">
                        {{-- Clock In button --}}
                        @if(!$todayAttendance || !$todayAttendance->clock_in)
                            <form method="POST" action="{{ route('attendance.clock-in') }}"
                                class="flex-fill">
                                @csrf
                                <button type="submit" class="btn btn-success w-100 btn-sm py-2"
                                    onclick="return confirm('確認上班打卡？')">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>上班打卡
                                </button>
                            </form>
                        @else
                            <button class="btn btn-sm w-100 py-2 flex-fill"
                                style="background:#d1e7dd; color:#0f5132;" disabled>
                                <i class="bi bi-check-circle me-1"></i>
                                已上班 {{ \Carbon\Carbon::parse($todayAttendance->clock_in)->format('H:i') }}
                            </button>
                        @endif

                        {{-- Clock Out button --}}
                        @if($todayAttendance?->clock_in && !$todayAttendance?->clock_out)
                            <form method="POST" action="{{ route('attendance.clock-out') }}"
                                class="flex-fill">
                                @csrf
                                <button type="submit" class="btn btn-warning w-100 btn-sm py-2 text-dark"
                                    onclick="return confirm('確認下班打卡？')">
                                    <i class="bi bi-box-arrow-right me-1"></i>下班打卡
                                </button>
                            </form>
                        @elseif($todayAttendance?->clock_out)
                            <button class="btn btn-sm w-100 py-2 flex-fill"
                                style="background:#d1e7dd; color:#0f5132;" disabled>
                                <i class="bi bi-check-circle me-1"></i>
                                已下班 {{ \Carbon\Carbon::parse($todayAttendance->clock_out)->format('H:i') }}
                            </button>
                        @else
                            <button class="btn btn-secondary btn-sm w-100 py-2 flex-fill" disabled>
                                <i class="bi bi-box-arrow-right me-1"></i>下班打卡
                            </button>
                        @endif
                    </div>

                    @if($todayAttendance?->status === 'late')
                    <div class="mt-2 text-warning" style="font-size:12px;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        今日遲到 {{ $todayAttendance->late_minutes }} 分鐘
                    </div>
                    @endif

                    @if($todayAttendance?->worked_hours)
                    <div class="mt-1 text-muted" style="font-size:12px;">
                        <i class="bi bi-clock me-1"></i>
                        今日工時：{{ $todayAttendance->worked_hours }} 小時
                    </div>
                    @endif
                </div>

                {{-- Other quick actions --}}
                <div class="d-grid gap-2">
                    <a href="{{ route('leave.create') }}" class="btn btn-navy btn-sm">
                        <i class="bi bi-plus-circle me-2"></i>申請請假
                    </a>
                    <a href="{{ route('overtime.create') }}" class="btn btn-teal btn-sm">
                        <i class="bi bi-clock-history me-2"></i>記錄加班
                    </a>
                    <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-calendar3 me-2"></i>打卡記錄
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent leave --}}
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header card-header-navy d-flex justify-content-between align-items-center py-2 px-3">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-calendar3 me-2"></i>近期請假申請
                </span>
                <a href="{{ route('leave.index') }}" class="text-white-50 small">查看全部 →</a>
            </div>
            <div class="card-body p-0">
                @if($recentLeave->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">尚無請假記錄</p>
                @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>假別</th>
                            <th>日期</th>
                            <th>天數</th>
                            <th>狀態</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentLeave as $leave)
                        @php
                            $status = $leave->status instanceof \App\Enums\LeaveStatus ? $leave->status->value : $leave->status;
                            $type   = $leave->leave_type instanceof \App\Enums\LeaveType ? $leave->leave_type->value : $leave->leave_type;
                        @endphp
                        <tr>
                            <td>{{ $type }}</td>
                            <td>{{ $leave->start_date->format('Y/m/d') }}</td>
                            <td>{{ $leave->hours ? $leave->hours.'小時' : $leave->days.'天' }}</td>
                            <td>
                                <span class="badge badge-{{ $status }}">{{ $status }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection