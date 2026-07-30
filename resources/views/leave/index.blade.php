@extends('layouts.app')
@section('page-title', '我的請假申請')

@section('content')

{{-- 簽核代理設定 — managers only --}}
@if($isManager)
<div class="card mb-4">
    <div class="card-header card-header-navy py-2 px-4 d-flex justify-content-between align-items-center">
        <span style="font-size:14px;font-weight:600;">
            <i class="bi bi-person-check me-2"></i>我的簽核代理設定
        </span>
        <button class="btn btn-sm btn-light" type="button"
            data-bs-toggle="collapse" data-bs-target="#delegationPanel"
            id="delegationToggleBtn" aria-expanded="true">
            <i class="bi bi-chevron-up" id="delegationChevron"></i>
        </button>
    </div>

    <div class="collapse show" id="delegationPanel">
        <div class="card-body">

            {{-- Active delegations --}}
            @php
                $activeDelegations = $myDelegations->filter(fn($d) =>
                    $d->is_active &&
                    \Carbon\Carbon::parse($d->end_date)->gte(now()->startOfDay())
                );
                $pastDelegations = $myDelegations->filter(fn($d) =>
                    !$d->is_active ||
                    \Carbon\Carbon::parse($d->end_date)->lt(now()->startOfDay())
                );
            @endphp

            @if($activeDelegations->isEmpty())
                <p class="text-muted mb-3">目前無有效的簽核代理設定。請假前請記得設定代理人。</p>
            @else
            <div class="mb-3">
                <h6 class="fw-semibold mb-2" style="font-size:13px; color:#1F3864;">
                    有效代理
                </h6>
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>代理人</th>
                            <th>代理期間</th>
                            <th>原因</th>
                            <th>狀態</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activeDelegations as $d)
                        <tr>
                            <td class="fw-semibold">
                                {{ $d->delegate->name }}
                                <span class="text-muted" style="font-size:11px;">
                                    （{{ $d->delegate->employee_no }}）
                                </span>
                            </td>
                            <td>
                                {{ \Carbon\Carbon::parse($d->start_date)->format('Y/m/d') }}
                                ~
                                {{ \Carbon\Carbon::parse($d->end_date)->format('Y/m/d') }}
                            </td>
                            <td class="text-muted">{{ $d->reason ?? '—' }}</td>
                            <td>
                                @php
                                    $today = now()->toDateString();
                                    $isNow = $d->start_date <= $today && $d->end_date >= $today;
                                @endphp
                                @if($isNow)
                                    <span class="badge bg-success">代理中</span>
                                @else
                                    <span class="badge bg-warning text-dark">待生效</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST"
                                    action="{{ route('delegations.deactivate', $d->id) }}">
                                    @csrf
                                    <button type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('確定撤銷此代理設定？')">
                                        撤銷
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Add new delegation --}}
            <h6 class="fw-semibold mb-3" style="font-size:13px; color:#1F3864;">
                <i class="bi bi-plus-circle me-1"></i>新增代理
            </h6>
            <form method="POST" action="{{ route('delegations.store') }}">
                @csrf
                <input type="hidden" name="delegator_id" value="{{ $employee->id }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">代理人 <span class="text-danger">*</span></label>
                        <select name="delegate_id" class="form-select">
                            <option value="">— 選擇員工 —</option>
                            @foreach($allEmployees as $e)
                                <option value="{{ $e->id }}">
                                    {{ $e->name }}（{{ $e->employee_no }}・{{ $e->department }}）
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">開始日期 <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control"
                            min="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">結束日期 <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">原因</label>
                        <input type="text" name="reason" class="form-control"
                            placeholder="例：出差、休假">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-navy w-100">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
            </form>

            {{-- Past delegations (collapsed) --}}
            @if($pastDelegations->isNotEmpty())
            <div class="mt-3">
                <button class="btn btn-sm btn-outline-secondary" type="button"
                    data-bs-toggle="collapse" data-bs-target="#pastDelegations">
                    查看歷史代理記錄（{{ $pastDelegations->count() }} 筆）
                </button>
                <div class="collapse mt-2" id="pastDelegations">
                    <table class="table table-sm text-muted mb-0">
                        <thead>
                            <tr><th>代理人</th><th>代理期間</th><th>原因</th></tr>
                        </thead>
                        <tbody>
                            @foreach($pastDelegations as $d)
                            <tr>
                                <td>{{ $d->delegate->name }}</td>
                                <td>
                                    {{ \Carbon\Carbon::parse($d->start_date)->format('Y/m/d') }}
                                    ~
                                    {{ \Carbon\Carbon::parse($d->end_date)->format('Y/m/d') }}
                                </td>
                                <td>{{ $d->reason ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
@endif

{{-- existing leave list content below --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-2">
        @foreach([''=>'全部','草稿'=>'草稿','簽核中'=>'簽核中','已核准'=>'已核准','已拒絕'=>'已拒絕'] as $val => $label)
            <a href="{{ route('leave.index', ['status'=>$val]) }}"
                class="btn btn-sm {{ request('status') === $val ? 'btn-navy' : 'btn-outline-secondary' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
    <a href="{{ route('leave.create') }}" class="btn btn-navy">
        <i class="bi bi-plus-circle me-1"></i> 新增申請
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        @if($leaves->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                目前沒有請假記錄
            </div>
        @else
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>假別</th>
                    <th>日期範圍</th>
                    <th>天數／時數</th>
                    <th>狀態</th>
                    <th>審核層級</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach($leaves as $leave)
                @php
                    $status = $leave->status instanceof \App\Enums\LeaveStatus ? $leave->status->value : $leave->status;
                    $type   = $leave->leave_type instanceof \App\Enums\LeaveType ? $leave->leave_type->value : $leave->leave_type;
                @endphp
                <tr>
                    <td><span class="fw-semibold">{{ $type }}</span></td>
                    <td>{{ $leave->start_date->format('Y/m/d') }} ~ {{ $leave->end_date->format('Y/m/d') }}</td>
                    <td>{{ $leave->hours ? $leave->hours.'小時' : $leave->days.'天' }}</td>
                    <td><span class="badge badge-{{ $status }} px-2 py-1">{{ $status }}</span></td>
                    <td class="text-muted small">
                        @if($leave->current_approver === 'manager') 部門主管審核中
                        @elseif($leave->current_approver === 'hr') 人資部審核中
                        @else —
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('leave.show', $leave->id) }}" class="btn btn-sm btn-outline-primary">查看</a>
                        @if($status === '草稿')
                            <a href="{{ route('leave.edit', $leave->id) }}" class="btn btn-sm btn-outline-secondary">編輯</a>
                            <form method="POST" action="{{ route('leave.destroy', $leave->id) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('確定刪除此草稿？')">刪除</button>
                            </form>
                        @endif
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
const delegationPanel = document.getElementById('delegationPanel');
const chevron         = document.getElementById('delegationChevron');

delegationPanel.addEventListener('show.bs.collapse', function () {
    chevron.classList.remove('bi-chevron-down');
    chevron.classList.add('bi-chevron-up');
});

delegationPanel.addEventListener('hide.bs.collapse', function () {
    chevron.classList.remove('bi-chevron-up');
    chevron.classList.add('bi-chevron-down');
});
</script>
@endsection

@endsection