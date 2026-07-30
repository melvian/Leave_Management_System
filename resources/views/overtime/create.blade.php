@extends('layouts.app')
@section('page-title', '新增加班記錄')

@section('content')

@if(session('info'))
<div class="alert alert-info alert-dismissible fade show mb-4">
    <i class="bi bi-clock-history me-2"></i>{{ session('info') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row justify-content-center">
<div class="col-lg-7">

<div class="alert alert-info d-flex align-items-center mb-4">
    <i class="bi bi-info-circle-fill me-2"></i>
    加班記錄送出後需等待主管確認，確認後時數將自動加入您的補休餘額。
</div>

<div class="card">
    <div class="card-header card-header-navy py-2 px-4">
        <span style="font-size:14px;font-weight:600;">
            <i class="bi bi-clock-history me-2"></i>填寫加班記錄
        </span>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="{{ route('overtime.store') }}">
            @csrf

            {{-- Overtime type --}}
            <div class="mb-4">
                <label class="form-label">加班性質 <span class="text-danger">*</span></label>
                <div class="d-flex gap-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="overtime_type"
                            id="type_work" value="work"
                            {{ old('overtime_type', 'work') === 'work' ? 'checked' : '' }}
                            onchange="checkOvertimeType()">
                        <label class="form-check-label" for="type_work">
                            <i class="bi bi-briefcase me-1"></i>工作需要
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="overtime_type"
                            id="type_personal" value="personal"
                            {{ old('overtime_type') === 'personal' ? 'checked' : '' }}
                            onchange="checkOvertimeType()">
                        <label class="form-check-label" for="type_personal">
                            <i class="bi bi-person me-1"></i>個人原因
                        </label>
                    </div>
                </div>
                <div id="personal_warning"
                    class="alert alert-warning d-flex align-items-center mt-2 py-2 mb-0"
                    style="display:none !important;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    個人原因不符合加班申請資格，系統不受理此類申請。
                </div>
            </div>

            {{-- Date --}}
            <div class="mb-3">
                <label class="form-label">加班日期 <span class="text-danger">*</span></label>
                <input type="date" name="date" class="form-control"
                    value="{{ old('date', $prefill['date'] ?? '') }}"
                    max="{{ date('Y-m-d') }}">
                <div class="form-text">只能選擇今天或過去的日期。</div>
            </div>

            {{-- Time range --}}
            <div class="row g-3 mb-3 align-items-end">
                <div class="col-auto">
                    <label class="form-label">開始時間 <span class="text-danger">*</span></label>
                    <select name="start_time" id="ot_start" class="form-select">
                        <option value="">— 選擇 —</option>
                        @php
                            $times = [];
                            for ($h = 18; $h <= 23; $h++) {
                                $times[] = sprintf('%02d:00', $h);
                                if ($h < 23) $times[] = sprintf('%02d:30', $h);
                            }
                            $prefillStart = $prefill['start'] ?? old('start_time', '');
                        @endphp
                        @foreach($times as $t)
                            <option value="{{ $t }}"
                                {{ $prefillStart === $t ? 'selected' : '' }}>
                                {{ $t }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto pb-1 text-muted">至</div>
                <div class="col-auto">
                    <label class="form-label">結束時間 <span class="text-danger">*</span></label>
                    <select name="end_time" id="ot_end" class="form-select">
                        <option value="">— 選擇 —</option>
                        @php
                            $prefillEnd = $prefill['end'] ?? old('end_time', '');
                        @endphp
                        @foreach($times as $t)
                            <option value="{{ $t }}"
                                {{ $prefillEnd === $t ? 'selected' : '' }}>
                                {{ $t }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto pb-1" id="ot_hours_display" style="display:none;">
                    <span class="badge bg-success fs-6 px-3 py-2">
                        共 <span id="ot_hours_result">0</span> 小時
                    </span>
                </div>
            </div>

            {{-- Reason --}}
            <div class="mb-4">
                <label class="form-label">加班事由 <span class="text-danger">*</span></label>
                <textarea name="overtime_reason" class="form-control" rows="3"
                    maxlength="300"
                    placeholder="請填寫加班原因（必填）">{{ old('overtime_reason') }}</textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" id="submit_btn" class="btn btn-teal">
                    <i class="bi bi-send me-1"></i> 送出加班記錄
                </button>
                <a href="{{ route('overtime.index') }}" class="btn btn-light ms-auto">取消</a>
            </div>
        </form>
    </div>
</div>

</div>
</div>
@endsection

@section('scripts')
<script>
function calcOtHours() {
    const start   = document.getElementById('ot_start').value;
    const end     = document.getElementById('ot_end').value;
    const display = document.getElementById('ot_hours_display');
    const result  = document.getElementById('ot_hours_result');
    if (start && end && end > start) {
        const [sh, sm] = start.split(':').map(Number);
        const [eh, em] = end.split(':').map(Number);
        const totalMins = (eh * 60 + em) - (sh * 60 + sm);
        result.textContent = Math.round((totalMins / 60) * 10) / 10;
        display.style.display = 'block';
    } else {
        display.style.display = 'none';
    }
}

function checkOvertimeType() {
    const isPersonal = document.getElementById('type_personal').checked;
    const warning    = document.getElementById('personal_warning');
    const submitBtn  = document.getElementById('submit_btn');

    if (isPersonal) {
        warning.style.display   = 'flex';
        submitBtn.disabled      = true;
        submitBtn.classList.add('disabled');
    } else {
        warning.style.display   = 'none';
        submitBtn.disabled      = false;
        submitBtn.classList.remove('disabled');
    }
}

document.getElementById('ot_start').addEventListener('change', calcOtHours);
document.getElementById('ot_end').addEventListener('change', calcOtHours);

// Run on load in case of old() repopulation
checkOvertimeType();
calcOtHours();
</script>
@endsection