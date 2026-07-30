@extends('layouts.app')
@section('page-title', '考勤管理')

@section('content')

{{-- Filter bar --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('attendance.management.index') }}"
            class="d-flex gap-3 align-items-center flex-wrap">
            <div>
                <label class="form-label mb-1 small fw-semibold">查詢日期</label>
                <input type="date" name="date" class="form-control form-control-sm"
                    style="width:180px;"
                    value="{{ $date }}" max="{{ now()->toDateString() }}">
            </div>
            <div>
                <label class="form-label mb-1 small fw-semibold">部門</label>
                <select name="dept" class="form-select form-select-sm" style="width:160px;">
                    <option value="">全部部門</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}"
                            {{ $selectedDept === $dept ? 'selected' : '' }}>
                            {{ $dept }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-navy btn-sm">
                    <i class="bi bi-search me-1"></i>查詢
                </button>
                <a href="{{ route('attendance.management.index') }}"
                    class="btn btn-outline-secondary btn-sm ms-1">重設</a>
            </div>

            {{-- CSV export — far right --}}
            <div class="mt-3 ms-auto">
                <a href="{{ route('attendance.management.export', ['date' => $date, 'dept' => $selectedDept]) }}"
                    class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-excel me-1"></i>匯出 Excel
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Summary cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <a href="{{ route('attendance.management.index', ['date'=>$date,'dept'=>$selectedDept,'filter'=>'normal']) }}"
            class="text-decoration-none">
            <div class="stat-card {{ $filter==='normal' ? 'border-3' : '' }}"
                style="border-left-color:#198754; cursor:pointer;
                {{ $filter==='normal' ? 'background:#f0fff4;' : '' }}">
                <div class="stat-label">正常出勤</div>
                <div class="stat-value" style="color:#198754;">{{ $summary['normal'] }}</div>
                <div class="stat-sub">人</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-2">
        <a href="{{ route('attendance.management.index', ['date'=>$date,'dept'=>$selectedDept,'filter'=>'late']) }}"
            class="text-decoration-none">
            <div class="stat-card {{ $filter==='late' ? 'border-3' : '' }}"
                style="border-left-color:#ffc107; cursor:pointer;
                {{ $filter==='late' ? 'background:#fffdf0;' : '' }}">
                <div class="stat-label">遲到</div>
                <div class="stat-value" style="color:#856404;">{{ $summary['late'] }}</div>
                <div class="stat-sub">人</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-2">
        <a href="{{ route('attendance.management.index', ['date'=>$date,'dept'=>$selectedDept,'filter'=>'early_leave']) }}"
            class="text-decoration-none">
            <div class="stat-card {{ $filter==='early_leave' ? 'border-3' : '' }}"
                style="border-left-color:#fd7e14; cursor:pointer;
                {{ $filter==='early_leave' ? 'background:#fff8f0;' : '' }}">
                <div class="stat-label">早退</div>
                <div class="stat-value" style="color:#fd7e14;">{{ $summary['early_leave'] }}</div>
                <div class="stat-sub">人</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="{{ route('attendance.management.index', ['date'=>$date,'dept'=>$selectedDept,'filter'=>'on_leave']) }}"
            class="text-decoration-none">
            <div class="stat-card {{ $filter==='on_leave' ? 'border-3' : '' }}"
                style="border-left-color:#0E7C86; cursor:pointer;
                {{ $filter==='on_leave' ? 'background:#f0fbfc;' : '' }}">
                <div class="stat-label">已請假</div>
                <div class="stat-value" style="color:#0E7C86;">{{ $summary['on_leave'] }}</div>
                <div class="stat-sub">人</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="{{ route('attendance.management.index', ['date'=>$date,'dept'=>$selectedDept,'filter'=>'absent']) }}"
            class="text-decoration-none">
            <div class="stat-card {{ $filter==='absent' ? 'border-3' : '' }}"
                style="border-left-color:#dc3545; cursor:pointer;
                {{ $filter==='absent' ? 'background:#fff5f5;' : '' }}">
                <div class="stat-label">未出勤（無假）</div>
                <div class="stat-value" style="color:#dc3545;">{{ $absentWithoutLeave->count() }}</div>
                <div class="stat-sub">人</div>
            </div>
        </a>
    </div>
</div>

{{-- Filtered content --}}
@if($filter === 'on_leave')
{{-- 已請假 --}}
<div class="card mb-4">
    <div class="card-header card-header-navy py-2 px-4">
        <span style="font-size:14px;font-weight:600;">
            <i class="bi bi-calendar-check me-2"></i>
            今日請假員工（{{ $onLeaveEmployees->count() }} 人）
        </span>
    </div>
    <div class="card-body p-0">
        @if($onLeaveEmployees->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>今日無員工請假
            </div>
        @else
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>員工</th>
                    <th>部門</th>
                    <th>假別</th>
                    <th>假期日期</th>
                    <th>天數/時數</th>
                    <th>事由</th>
                </tr>
            </thead>
            <tbody>
                @foreach($onLeaveEmployees as $emp)
                @php
                    $leaveRecord = \App\Models\LeaveRequest::where('employee_id', $emp->id)
                        ->where('status', '已核准')
                        ->whereDate('start_date', '<=', $date)
                        ->whereDate('end_date', '>=', $date)
                        ->first();
                    $lt = $leaveRecord?->leave_type instanceof \App\Enums\LeaveType
                        ? $leaveRecord->leave_type->value
                        : $leaveRecord?->leave_type;
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $emp->name }}
                        <div class="text-muted" style="font-size:11px;">{{ $emp->employee_no }}</div>
                    </td>
                    <td>{{ $emp->department }}</td>
                    <td><span class="badge bg-info text-dark">{{ $lt }}</span></td>
                    <td>
                        {{ $leaveRecord?->start_date->format('Y/m/d') }}
                        ~ {{ $leaveRecord?->end_date->format('Y/m/d') }}
                    </td>
                    <td>
                        {{ $leaveRecord?->hours
                            ? $leaveRecord->hours.'小時 ('.$leaveRecord->start_time.' – '.$leaveRecord->end_time.')'
                            : $leaveRecord?->days.'天' }}
                    </td>
                    <td class="text-muted">{{ $leaveRecord?->leave_reason ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

@elseif($filter === 'absent')
{{-- 未出勤 --}}
<div class="card">
    <div class="card-header card-header-navy py-2 px-4">
        <span style="font-size:14px;font-weight:600;">
            <i class="bi bi-person-x me-2"></i>
            未出勤員工（無請假記錄）（{{ $absentWithoutLeave->count() }} 人）
        </span>
    </div>
    <div class="card-body p-0">
        @if($absentWithoutLeave->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-check-circle fs-1 d-block mb-2"></i>
                今日所有員工均已出勤或請假
            </div>
        @else
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>員工</th>
                    <th>部門</th>
                    <th>員工編號</th>
                    <th>角色</th>
                    <th>備註</th>
                </tr>
            </thead>
            <tbody>
                @foreach($absentWithoutLeave as $emp)
                @php
                    $empRole = $emp->role instanceof \App\Enums\Role
                        ? $emp->role->value : $emp->role;
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('employee.show', $emp->id) }}"
                            class="fw-semibold text-decoration-none">
                            {{ $emp->name }}
                        </a>
                    </td>
                    <td>{{ $emp->department }}</td>
                    <td style="font-family:monospace;">{{ $emp->employee_no }}</td>
                    <td><span class="badge bg-secondary">{{ $empRole }}</span></td>
                    <td class="text-danger small">未打卡且無核准請假</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

@else
{{-- Default: attendance records table --}}
<div class="card">
    <div class="card-header card-header-navy py-2 px-4 d-flex justify-content-between align-items-center">
        <span style="font-size:14px;font-weight:600;">
            <i class="bi bi-people me-2"></i>
            {{ \Carbon\Carbon::parse($date)->format('Y/m/d') }} 出勤記錄
            @if($selectedDept) — {{ $selectedDept }} @endif
            <span class="ms-2 text-white-50" style="font-size:12px;">
                共 {{ $filteredRecords->count() }} 筆
            </span>
        </span>
    </div>
    <div class="card-body p-0">
        @if($filteredRecords->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                該日期尚無打卡記錄
            </div>
        @else
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>員工</th>
                    <th>部門</th>
                    <th>上班打卡</th>
                    <th>下班打卡</th>
                    <th>實際工時</th>
                    <th>遲到</th>
                    <th>早退</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach($filteredRecords as $record)
                <tr>
                    <td>
                        <a href="{{ route('employee.show', $record->employee->id) }}"
                            class="fw-semibold text-decoration-none">
                            {{ $record->employee->name }}
                        </a>
                        <div class="text-muted" style="font-size:11px;">
                            {{ $record->employee->employee_no }}
                        </div>
                    </td>
                    <td>{{ $record->employee->department }}</td>
                    <td>
                        {{ $record->clock_in
                            ? \Carbon\Carbon::parse($record->clock_in)->format('H:i')
                            : '—' }}
                    </td>
                    <td>
                        @if($record->clock_out)
                            {{ \Carbon\Carbon::parse($record->clock_out)->format('H:i') }}
                        @else
                            <span class="text-warning">未下班打卡</span>
                        @endif
                    </td>
                    <td>
                        @if($record->worked_hours !== null && $record->worked_hours > 0)
                            {{ $record->worked_hours }} 小時
                        @else
                            —
                        @endif
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
                    <td>
                        <button class="btn btn-sm btn-outline-secondary"
                            onclick="openEditModal(
                                {{ $record->id }},
                                '{{ $record->clock_in ? \Carbon\Carbon::parse($record->clock_in)->format('H:i') : '' }}',
                                '{{ $record->clock_out ? \Carbon\Carbon::parse($record->clock_out)->format('H:i') : '' }}'
                            )">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endif

{{-- Edit modal --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header card-header-navy">
                <h5 class="modal-title text-white">
                    <i class="bi bi-pencil me-2"></i>修正打卡時間
                </h5>
                <button type="button" class="btn-close btn-close-white"
                    data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('attendance.management.correct') }}">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="record_id" id="edit_record_id">
                    <input type="hidden" name="date" value="{{ $date }}">
                    <input type="hidden" name="dept" value="{{ $selectedDept }}">
                    <div class="mb-3">
                        <label class="form-label">上班打卡時間</label>
                        <input type="time" name="clock_in" id="edit_clock_in" class="form-control">
                        <div class="form-text">留空則清除打卡記錄</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">下班打卡時間</label>
                        <input type="time" name="clock_out" id="edit_clock_out" class="form-control">
                        <div class="form-text">留空則清除打卡記錄</div>
                    </div>
                    <div class="alert alert-warning d-flex align-items-center mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        修正記錄將被系統記錄，請確認修正原因合理。
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-navy">儲存修正</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function openEditModal(recordId, clockIn, clockOut) {
    document.getElementById('edit_record_id').value = recordId;
    document.getElementById('edit_clock_in').value  = clockIn;
    document.getElementById('edit_clock_out').value = clockOut;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
@endsection