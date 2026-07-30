@extends('layouts.app')
@section('page-title', '審核請假申請')

@section('content')
<div class="mb-3">
    <a href="{{ route('approval.leave.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> 返回佇列
    </a>
</div>

@php
    $leaveType   = $leave->leave_type instanceof \App\Enums\LeaveType ? $leave->leave_type->value : $leave->leave_type;
    $leaveStatus = $leave->status instanceof \App\Enums\LeaveStatus ? $leave->status->value : $leave->status;
    $emp         = $leave->employee;
@endphp

<div class="row g-4">

    {{-- Left: info --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-file-text me-2"></i>申請資料
                </span>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-3">
                    <tbody>
                        @foreach([
                            '申請人'   => $emp->name.' ('.$emp->employee_no.')',
                            '部門'     => $emp->department,
                            '假別'     => $leaveType,
                            '開始日期' => $leave->start_date->format('Y/m/d'),
                            '結束日期' => $leave->end_date->format('Y/m/d'),
                            '天數'     => $leave->hours ? $leave->hours.'小時' : $leave->days.'天',
                            '事由'     => $leave->leave_reason ?? '—',
                            '送出時間' => $leave->created_at->format('Y/m/d H:i'),
                        ] as $label => $value)
                        <tr>
                            <td class="text-muted fw-semibold" style="width:110px;">{{ $label }}</td>
                            <td class="fw-semibold">{{ $value }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Employee balance --}}
                <div class="info-box">
                    <div class="fw-semibold mb-2">
                        <i class="bi bi-person-check me-1"></i> 申請人假期餘額
                    </div>
                    <div class="row g-3">
                        <div class="col-auto">
                            <span class="text-muted small">特別休假剩餘</span>
                            <div class="fw-bold" style="color:#1F3864;">{{ $emp->remainingAnnualLeave() }} 天</div>
                        </div>
                        <div class="col-auto">
                            <span class="text-muted small">補休餘額</span>
                            <div class="fw-bold" style="color:#0E7C86;">{{ $emp->compensatory_hours_remaining }} 小時</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: actions --}}
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-pen me-2"></i>審核操作
                </span>
            </div>
            <div class="card-body">

                {{-- Approve --}}
                <form method="POST" action="{{ route('approval.leave.approve', $leave->id) }}"
                    class="mb-3">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">備註（選填）</label>
                        <textarea name="admin_note" class="form-control" rows="3"
                            placeholder="可填寫核准備註（選填）"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2"
                        onclick="return confirm('確認核准此申請？')">
                        <i class="bi bi-check-circle me-2"></i>核准
                    </button>
                    @if($leave->days > 3)
                    <div class="form-text mt-2 text-center">
                        ※ 超過3天，核准後將轉交人資部進行最終審核
                    </div>
                    @endif
                </form>

                <hr>

                {{-- Reject --}}
                <form method="POST" action="{{ route('approval.leave.reject', $leave->id) }}"
                    onsubmit="return validateReject()">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label text-danger">
                            拒絕原因 <span class="text-danger">*</span>
                        </label>
                        <textarea name="admin_note" id="reject_note" class="form-control border-danger" rows="3"
                            placeholder="拒絕時必須填寫原因，員工將可查看"></textarea>
                        <div class="form-text text-danger">拒絕申請時必須填寫原因。</div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 py-2">
                        <i class="bi bi-x-circle me-2"></i>拒絕
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
function validateReject() {
    const note = document.getElementById('reject_note').value.trim();
    if (!note) { alert('拒絕申請時必須填寫原因。'); return false; }
    return confirm('確認拒絕此申請？員工將看到您填寫的原因。');
}
</script>
@endsection