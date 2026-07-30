@extends('layouts.app')
@section('page-title', '確認加班記錄')

@section('content')
<div class="mb-3">
    <a href="{{ route('approval.overtime.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> 返回佇列
    </a>
</div>

@php
    $status = $record->status instanceof \App\Enums\OvertimeStatus
        ? $record->status->value : $record->status;
    $emp = $record->employee;
@endphp

<div class="row g-4">

    {{-- Left: info --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-clock-history me-2"></i>加班資料
                </span>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-3">
                    <tbody>
                        @foreach([
                            '員工姓名' => $emp->name.' ('.$emp->employee_no.')',
                            '部門'     => $emp->department,
                            '加班日期' => $record->date->format('Y/m/d'),
                            '加班時段' => $record->start_time.' – '.$record->end_time,
                            '時數'     => $record->hours.' 小時',
                            '加班事由' => $record->overtime_reason ?? '—',
                            '送出時間' => $record->created_at->format('Y/m/d H:i'),
                        ] as $label => $value)
                        <tr>
                            <td class="text-muted fw-semibold" style="width:110px;">{{ $label }}</td>
                            <td class="fw-semibold">{{ $value }}</td>
                        </tr>
                        @endforeach
                        <tr>
                            <td class="text-muted fw-semibold">目前狀態</td>
                            <td><span class="badge badge-{{ $status }} px-2 py-1">{{ $status }}</span></td>
                        </tr>
                    </tbody>
                </table>

                <div class="info-box" style="border-left-color:#0E7C86;">
                    <div class="fw-semibold mb-1">
                        <i class="bi bi-hourglass-split me-1"></i> 該員工補休餘額
                    </div>
                    <div class="d-flex gap-4">
                        <div>
                            <span class="text-muted small">目前餘額</span>
                            <div class="fw-bold fs-5" style="color:#0E7C86;">
                                {{ $emp->compensatory_hours_remaining }} 小時
                            </div>
                        </div>
                        @if($status === '待確認')
                        <div>
                            <span class="text-muted small">確認後將變為</span>
                            <div class="fw-bold fs-5 text-success">
                                {{ $emp->compensatory_hours_remaining + $record->hours }} 小時
                            </div>
                        </div>
                        @endif
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
                @if($status === '待確認')

                {{-- Confirm --}}
                <form method="POST" action="{{ route('approval.overtime.confirm', $record->id) }}"
                    class="mb-3">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">備註（選填）</label>
                        <textarea name="admin_note" class="form-control" rows="3"
                            placeholder="可填寫確認備註（選填）"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2"
                        onclick="return confirm('確認此加班記錄？時數將自動加入補休餘額。')">
                        <i class="bi bi-check-circle me-2"></i>確認加班
                    </button>
                    <div class="form-text text-center mt-2">
                        確認後 {{ $record->hours }} 小時將加入補休餘額
                    </div>
                </form>

                <hr>

                {{-- Reject --}}
                <form method="POST" action="{{ route('approval.overtime.reject', $record->id) }}"
                    onsubmit="return validateOtReject()">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label text-danger">
                            駁回原因 <span class="text-danger">*</span>
                        </label>
                        <textarea name="admin_note" id="ot_reject_note" class="form-control border-danger" rows="3"
                            placeholder="駁回時必須填寫原因，員工將可查看"></textarea>
                        <div class="form-text text-danger">駁回時必須填寫原因。</div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 py-2">
                        <i class="bi bi-x-circle me-2"></i>駁回
                    </button>
                </form>

                @else
                <div class="text-center py-4">
                    <span class="badge badge-{{ $status }} px-3 py-2 fs-6">{{ $status }}</span>
                    @if($record->admin_note)
                    <div class="mt-3 text-muted small">{{ $record->admin_note }}</div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

</div>

<script>
function validateOtReject() {
    const note = document.getElementById('ot_reject_note').value.trim();
    if (!note) { alert('駁回時必須填寫原因。'); return false; }
    return confirm('確認駁回此加班記錄？');
}
</script>
@endsection