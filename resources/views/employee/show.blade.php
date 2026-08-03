@extends('layouts.app')
@section('page-title', '員工資料')

@section('content')
<div class="mb-3">
    <a href="{{ route('employee.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> 返回清單
    </a>
</div>

@php
    $role    = $emp->role instanceof \App\Enums\Role ? $emp->role->value : $emp->role;
    $diff    = $emp->hire_date->diff(now());
    $myRole  = Auth::user()->role instanceof \App\Enums\Role
        ? Auth::user()->role->value : Auth::user()->role;
    $canManage = in_array($myRole, ['人資部','系統管理者'])
        || ($myRole === '部門主管' && Auth::user()->department === '人資部');
@endphp

{{-- Row 1: 基本資料 | 假期餘額 --}}
<div class="row g-4 mb-4">

    {{-- 基本資料 --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-person-badge me-2"></i>基本資料
                </span>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-3">
                    @foreach([
                        '員工編號' => $emp->employee_no,
                        '姓名'     => $emp->name,
                        '性別'     => $emp->gender === 'male' ? '男' : '女',
                        '部門'     => $emp->department,
                        '角色'     => $role,
                        '到職日期' => $emp->hire_date->format('Y/m/d'),
                        '年資'     => $diff->y.'年'.$diff->m.'個月',
                        '帳號狀態' => $emp->is_active ? '啟用中' : '已停用',
                    ] as $label => $value)
                        <tr>
                            <td class="text-muted fw-semibold" style="width:90px;">{{ $label }}</td>
                            <td class="fw-semibold">{{ $value }}</td>
                        </tr>
                    @endforeach

                    {{-- Line binding status (separate row outside loop) --}}
                    <tr>
                        <td class="text-muted fw-semibold" style="width:90px;">Line 綁定</td>
                        <td>
                            @if($emp->line_user_id)
                                <span class="badge" style="background:#06C755; font-size:11px;">
                                    <i class="bi bi-check-circle me-1"></i>已綁定
                                </span>
                                <span class="text-muted ms-2"
                                    style="font-family:monospace; font-size:11px;">
                                    {{ Str::limit($emp->line_user_id, 12, '...') }}
                                </span>
                            @else
                                <span class="badge bg-secondary" style="font-size:11px;">
                                    <i class="bi bi-dash-circle me-1"></i>未綁定
                                </span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                </table>
                @if($canManage)
                <div class="d-flex gap-2">
                    <a href="{{ route('employee.edit', $emp->id) }}" class="btn btn-sm btn-navy">
                        <i class="bi bi-pencil me-1"></i>編輯
                    </a>
                    @if($emp->is_active)
                    <form method="POST" action="{{ route('employee.deactivate', $emp->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('確定停用此帳號？')">
                            <i class="bi bi-person-x me-1"></i>停用
                        </button>
                    </form>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- 假期餘額 --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-bar-chart me-2"></i>假期餘額
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="stat-card" style="border-left-color:#2E74B5;">
                            <div class="stat-label">特別休假剩餘</div>
                            <div class="stat-value">{{ $emp->remainingAnnualLeave() }}</div>
                            <div class="stat-sub">天（年度應得 {{ $emp->annualLeaveEntitlement() }} 天）</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card" style="border-left-color:#0E7C86;">
                            <div class="stat-label">補休餘額</div>
                            <div class="stat-value" style="color:#0E7C86;">{{ $emp->compensatory_hours_remaining }}</div>
                            <div class="stat-sub">小時</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card" style="border-left-color:#E36C09;">
                            <div class="stat-label">病假已請</div>
                            <div class="stat-value">{{ $emp->usedSickLeave() }}</div>
                            <div class="stat-sub">天（上限 30 天）</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card" style="border-left-color:#888;">
                            <div class="stat-label">事假已請</div>
                            <div class="stat-value">{{ $emp->usedPersonalLeave() }}</div>
                            <div class="stat-sub">天（上限 14 天）</div>
                        </div>
                    </div>
                    @if($emp->gender === 'female')
                    <div class="col-6">
                        <div class="stat-card" style="border-left-color:#C2486A;">
                            <div class="stat-label">本月生理假</div>
                            <div class="stat-value" style="color:#C2486A;">{{ $emp->usedMenstrualLeaveThisMonth() }}</div>
                            <div class="stat-sub">天（上限 1 天）</div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Row 2: 請假歷史 | 加班歷史 side by side --}}
<div class="row g-4">

    {{-- 請假歷史 --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-calendar3 me-2"></i>請假歷史
                </span>
            </div>
            <div class="card-body p-0">
                @if($leaveHistory->isEmpty())
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
                        @foreach($leaveHistory as $leave)
                        @php
                            $ls = $leave->status instanceof \App\Enums\LeaveStatus
                                ? $leave->status->value : $leave->status;
                            $lt = $leave->leave_type instanceof \App\Enums\LeaveType
                                ? $leave->leave_type->value : $leave->leave_type;
                        @endphp
                        <tr>
                            <td>{{ $lt }}</td>
                            <td style="font-size:13px;">
                                {{ $leave->start_date->format('Y/m/d') }}
                                ~ {{ $leave->end_date->format('Y/m/d') }}
                            </td>
                            <td>{{ $leave->hours ? $leave->hours.'小時' : $leave->days.'天' }}</td>
                            <td><span class="badge badge-{{ $ls }}">{{ $ls }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>

    {{-- 加班歷史 --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-clock-history me-2"></i>加班歷史
                </span>
            </div>
            <div class="card-body p-0">
                @if($otHistory->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">尚無加班記錄</p>
                @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>日期</th>
                            <th>時段</th>
                            <th>時數</th>
                            <th>狀態</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($otHistory as $record)
                        @php
                            $os = $record->status instanceof \App\Enums\OvertimeStatus
                                ? $record->status->value : $record->status;
                        @endphp
                        <tr>
                            <td>{{ $record->date->format('Y/m/d') }}</td>
                            <td style="font-size:13px;">
                                {{ $record->start_time }} – {{ $record->end_time }}
                            </td>
                            <td>{{ $record->hours }} 小時</td>
                            <td><span class="badge badge-{{ $os }}">{{ $os }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- 簽核代理設定 — Admin only, managers only --}}
@php
    $empRole = $emp->role instanceof \App\Enums\Role ? $emp->role->value : $emp->role;
@endphp
@if($myRole === '系統管理者' && $empRole === '部門主管')
<div class="card mt-4">
    <div class="card-header card-header-navy py-2 px-4">
        <span style="font-size:14px;font-weight:600;">
            <i class="bi bi-person-check me-2"></i>簽核代理設定
        </span>
    </div>
    <div class="card-body">
        @php
            $delegations = \App\Models\Delegation::with('delegate')
                ->where('delegator_id', $emp->id)
                ->where('is_active', true)
                ->where('end_date', '>=', now()->toDateString())
                ->get();
        @endphp

        @if($delegations->isEmpty())
            <p class="text-muted mb-3">目前無有效的簽核代理設定。</p>
        @else
        <table class="table table-sm mb-3">
            <thead>
                <tr><th>代理人</th><th>代理期間</th><th>原因</th><th>操作</th></tr>
            </thead>
            <tbody>
                @foreach($delegations as $d)
                <tr>
                    <td>{{ $d->delegate->name }}（{{ $d->delegate->employee_no }}）</td>
                    <td>{{ $d->start_date->format('Y/m/d') }} ~ {{ $d->end_date->format('Y/m/d') }}</td>
                    <td>{{ $d->reason ?? '—' }}</td>
                    <td>
                        <form method="POST"
                            action="{{ route('delegations.deactivate', $d->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                撤銷
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <h6 class="fw-bold mb-3">新增代理</h6>
        <form method="POST" action="{{ route('delegations.store') }}">
            @csrf
            <input type="hidden" name="delegator_id" value="{{ $emp->id }}">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">代理人 <span class="text-danger">*</span></label>
                    <select name="delegate_id" class="form-select">
                        <option value="">— 選擇員工 —</option>
                        @foreach(\App\Models\Employee::where('id','!=',$emp->id)->where('is_active',true)->orderBy('name')->get() as $e)
                            <option value="{{ $e->id }}">
                                {{ $e->name }}（{{ $e->employee_no }}）
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">開始日期 <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control"
                        min="{{ date('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">結束日期 <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">代理原因</label>
                    <input type="text" name="reason" class="form-control"
                        placeholder="例：主管出差、休假">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-navy">
                        <i class="bi bi-plus-circle me-1"></i>建立代理
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@endsection