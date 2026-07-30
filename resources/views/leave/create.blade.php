@extends('layouts.app')
@section('page-title', isset($leave) ? '編輯草稿' : '新增請假申請')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-8">

{{-- Balance info bar --}}
<div class="info-box mb-4">
    <div class="row g-3">
        <div class="col-auto">
            <span class="text-muted small">特別休假剩餘</span>
            <div class="fw-bold" style="color:#1F3864;">{{ $balances['annual_remaining'] }} 天</div>
        </div>
        <div class="col-auto">
            <span class="text-muted small">病假已請</span>
            <div class="fw-bold">{{ $balances['sick_used'] }}/30 天</div>
        </div>
        <div class="col-auto">
            <span class="text-muted small">事假已請</span>
            <div class="fw-bold">{{ $balances['personal_used'] }}/14 天</div>
        </div>
        <div class="col-auto">
            <span class="text-muted small">補休餘額</span>
            <div class="fw-bold" style="color:#0E7C86;">{{ $balances['compensatory_hours'] }} 小時</div>
        </div>
        @if($employee->gender === 'female')
        <div class="col-auto">
            <span class="text-muted small">本月生理假</span>
            <div class="fw-bold" style="color:#C2486A;">{{ $balances['menstrual_used'] ?? 0 }}/1 天</div>
        </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header card-header-navy py-2 px-4">
        <span style="font-size:14px;font-weight:600;">
            <i class="bi bi-calendar-plus me-2"></i>
            {{ isset($leave) ? '編輯草稿申請' : '填寫請假申請' }}
        </span>
    </div>
    <div class="card-body p-4">

        <form method="POST"
            action="{{ isset($leave) ? route('leave.update', $leave->id) : route('leave.store') }}">
            @csrf
            @if(isset($leave)) @method('PUT') @endif

            {{-- Leave type --}}
            <div class="mb-3">
                <label class="form-label">假別 <span class="text-danger">*</span></label>
                <select name="leave_type" class="form-select" id="leave_type">
                    <option value="">— 請選擇假別 —</option>
                    @foreach($leaveTypes as $type)
                        @if($type->value === '生理假' && $employee->gender !== 'female')
                            @continue
                        @endif
                        <option value="{{ $type->value }}"
                            {{ old('leave_type', isset($leave) ? ($leave->leave_type instanceof \App\Enums\LeaveType ? $leave->leave_type->value : $leave->leave_type) : '') === $type->value ? 'selected' : '' }}>
                            {{ $type->value }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Dates --}}
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">開始日期 <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" id="start_date" class="form-control"
                        min="{{ date('Y-m-d') }}"
                        value="{{ old('start_date', isset($leave) ? $leave->start_date->format('Y-m-d') : '') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">結束日期 <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" id="end_date" class="form-control"
                        min="{{ date('Y-m-d') }}"
                        value="{{ old('end_date', isset($leave) ? $leave->end_date->format('Y-m-d') : '') }}">
                </div>
            </div>

            {{-- Day count display --}}
            <div id="day_count" class="mb-3" style="display:none;">
                <div class="info-box d-inline-block px-3 py-2">
                    共 <strong id="day_count_number">0</strong> 個工作天
                    <span class="text-muted small ms-1">（已排除週末）</span>
                </div>
            </div>

            {{-- Time-based (single day only) --}}
            <div id="hours_section" class="mb-3 p-3 border rounded" style="display:none; background:#f8f9fa;">
                <label class="form-label">請假時段（選填，僅限單日）</label>
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <select name="start_time" id="start_time" class="form-select">
                            <option value="">開始時間</option>
                            @php
                                $times = [];
                                for ($h = 9; $h <= 17; $h++) {
                                    $times[] = sprintf('%02d:00', $h);
                                    $times[] = sprintf('%02d:30', $h);
                                }
                                $times[] = '18:00';
                            @endphp
                            @foreach($times as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto text-muted">至</div>
                    <div class="col-auto">
                        <select name="end_time" id="end_time" class="form-select">
                            <option value="">結束時間</option>
                            @foreach($times as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto" id="hours_display" style="display:none;">
                        <span class="badge bg-primary fs-6">
                            共 <span id="hours_result">0</span> 小時
                        </span>
                        <div class="text-muted small mt-1">已扣除午休（12:00–13:00）</div>
                    </div>
                </div>
                <div class="form-text">工作時間 09:00–18:00。不填時段則以整天計算。</div>
            </div>

            {{-- Reason --}}
            <div class="mb-4">
                <label class="form-label">事由 <span class="text-danger">*</span></label>
                <textarea name="leave_reason" class="form-control" rows="3" maxlength="500"
                    placeholder="請填寫請假事由">{{ old('leave_reason', isset($leave) ? $leave->leave_reason : '') }}</textarea>
            </div>

            {{-- Actions --}}
            <div class="d-flex gap-2">
                <button type="submit" name="action" value="draft" class="btn btn-outline-secondary">
                    <i class="bi bi-save me-1"></i> 暫存草稿
                </button>
                <button type="submit" name="action" value="submit" class="btn btn-navy">
                    <i class="bi bi-send me-1"></i> 送出申請
                </button>
                <a href="{{ route('leave.index') }}" class="btn btn-light ms-auto">取消</a>
            </div>
        </form>
    </div>
</div>

</div>
</div>

@endsection

@section('scripts')
<script>
function isWeekend(dateStr) {
    const d = new Date(dateStr);
    return d.getDay() === 0 || d.getDay() === 6;
}
function countWeekdays(startStr, endStr) {
    let count = 0;
    const cur = new Date(startStr);
    const end = new Date(endStr);
    while (cur <= end) {
        if (cur.getDay() !== 0 && cur.getDay() !== 6) count++;
        cur.setDate(cur.getDate() + 1);
    }
    return count;
}
function calcHours() {
    const startTime = document.getElementById('start_time').value;
    const endTime   = document.getElementById('end_time').value;
    const display   = document.getElementById('hours_display');
    const result    = document.getElementById('hours_result');
    if (startTime && endTime && endTime > startTime) {
        const [sh,sm] = startTime.split(':').map(Number);
        const [eh,em] = endTime.split(':').map(Number);
        const startMins = sh*60+sm, endMins = eh*60+em;
        let totalMins = endMins - startMins;
        const overlapStart = Math.max(startMins, 720);
        const overlapEnd   = Math.min(endMins, 780);
        if (overlapEnd > overlapStart) totalMins -= (overlapEnd - overlapStart);
        result.textContent = Math.round((totalMins/60)*10)/10;
        display.style.display = 'block';
    } else {
        display.style.display = 'none';
    }
}
function checkDates() {
    const startInput = document.getElementById('start_date');
    const endInput   = document.getElementById('end_date');
    const start = startInput.value, end = endInput.value;
    const type  = document.getElementById('leave_type').value;
    const section    = document.getElementById('hours_section');
    const dayDisplay = document.getElementById('day_count');
    const dayNumber  = document.getElementById('day_count_number');
    const noHoursTypes = ['生理假','公假'];
    const today = new Date(); today.setHours(0,0,0,0);
    if (start) {
        const sd = new Date(start); sd.setHours(0,0,0,0);
        if (sd < today) { alert('開始日期不可選擇過去的日期。'); startInput.value=''; return; }
        if (isWeekend(start)) { alert('開始日期不可選擇週末，請選擇工作日。'); startInput.value=''; return; }
    }
    if (end && isWeekend(end)) { alert('結束日期不可選擇週末，請選擇工作日。'); endInput.value=''; return; }
    if (start && end && end < start) { alert('結束日期不可早於開始日期。'); endInput.value=''; return; }
    if (start && end && start <= end) {
        const wd = countWeekdays(start, end);
        dayNumber.textContent = wd;
        dayDisplay.style.display = wd > 0 ? 'block' : 'none';
        section.style.display = (start===end && !isWeekend(start) && !noHoursTypes.includes(type)) ? 'block' : 'none';
    } else {
        dayDisplay.style.display = 'none';
        section.style.display = 'none';
    }
}
document.getElementById('start_date').addEventListener('change', checkDates);
document.getElementById('end_date').addEventListener('change', checkDates);
document.getElementById('leave_type').addEventListener('change', checkDates);
document.getElementById('start_time').addEventListener('change', calcHours);
document.getElementById('end_time').addEventListener('change', calcHours);
checkDates();
</script>
@endsection