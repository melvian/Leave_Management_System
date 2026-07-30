@extends('layouts.app') 
@section('page-title', '部門管理')

@section('content')
<div class="row justify-content-center">
<div class="col-lg-9">

<div class="card mb-4">
    <div class="card-header card-header-navy py-2 px-4">
        <span style="font-size:14px;font-weight:600;">
            <i class="bi bi-diagram-2 me-2"></i>部門清單
        </span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>部門名稱</th>
                    <th>員工人數</th>
                    <th>上班時間</th>
                    <th>下班時間</th>
                    <th>遲到容忍</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach($departments as $dept)
                @php
                    $shift     = $shifts[$dept] ?? null;
                    $shStart   = $shift?->shift_start   ?? '09:00';
                    $shEnd     = $shift?->shift_end     ?? '18:00';
                    $tolerance = $shift?->late_tolerance ?? 10;
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $dept }}</td>
                    <td>{{ $deptCounts[$dept] ?? 0 }} 人</td>
                    <td>{{ $shStart }}</td>
                    <td>{{ $shEnd }}</td>
                    <td>{{ $tolerance }} 分鐘</td>
                    <td>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-secondary"
                                onclick="openEditModal(
                                    '{{ $dept }}',
                                    '{{ $shStart }}',
                                    '{{ $shEnd }}',
                                    '{{ $tolerance }}'
                                )">
                                <i class="bi bi-pencil me-1"></i>編輯
                            </button>
                            @if(($deptCounts[$dept] ?? 0) === 0)
                            <form method="POST" action="{{ route('departments.destroy') }}"
                                class="d-inline">
                                @csrf
                                <input type="hidden" name="name" value="{{ $dept }}">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('確定刪除「{{ $dept }}」？')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-3 py-2 text-muted small border-top">
            <i class="bi bi-lightbulb me-1"></i>
            新增部門請透過
            <a href="{{ route('employee.create') }}">新增員工</a>
            時輸入新部門名稱。未設定班次的部門預設使用 09:00–18:00。
        </div>
    </div>
</div>

</div>
</div>

{{-- Edit modal — rename + shift --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header card-header-navy">
                <h5 class="modal-title text-white">
                    <i class="bi bi-pencil-square me-2"></i>編輯部門
                    — <span id="modal_dept_label"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white"
                    data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">

                {{-- Section 1: Rename --}}
                <div class="mb-4 pb-4" style="border-bottom:1px solid #eee;">
                    <h6 class="fw-bold mb-3" style="color:#1F3864;">
                        <i class="bi bi-tag me-2"></i>部門名稱
                    </h6>
                    <form method="POST" action="{{ route('departments.update') }}"
                        id="renameForm">
                        @csrf
                        <input type="hidden" name="old_name" id="rename_old_name">
                        <div class="d-flex gap-3 align-items-end">
                            <div class="flex-fill">
                                <label class="form-label">新部門名稱</label>
                                <input type="text" name="new_name" id="rename_new_name"
                                    class="form-control"
                                    placeholder="輸入新名稱">
                            </div>
                            <button type="submit" class="btn btn-navy"
                                onclick="return confirm('確定更名？此操作將影響所有屬於此部門的員工。')">
                                <i class="bi bi-check-lg me-1"></i>儲存名稱
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Section 2: Shift settings --}}
                <div>
                    <h6 class="fw-bold mb-3" style="color:#1F3864;">
                        <i class="bi bi-clock me-2"></i>班次設定
                    </h6>
                    <form method="POST" action="{{ route('departments.shift.update') }}"
                        id="shiftForm">
                        @csrf
                        <input type="hidden" name="department" id="shift_department">
                        <div class="row g-3">

                            <!-- Start -->
                            <div class="col-md-3">
                                <label class="form-label">上班時間</label>

                                <div class="d-flex align-items-center gap-2">

                                    <span class="fw-semibold" style="width:40px;">
                                        上午
                                    </span>

                                    <select id="start_hour" class="form-select">
                                        <option value="7">7</option>
                                        <option value="8">8</option>
                                        <option value="9">9</option>
                                        <option value="10">10</option>
                                    </select>

                                    <span>：</span>

                                    <select id="start_minute" class="form-select">
                                        <option value="00">00</option>
                                        <option value="30">30</option>
                                    </select>

                                </div>

                                <input
                                    type="hidden"
                                    name="shift_start"
                                    id="shift_start">
                            </div>

                            <!-- End -->
                            <div class="col-md-3">
                                <label class="form-label">下班時間</label>

                                <div class="d-flex align-items-center gap-2">

                                    <span class="fw-semibold" style="width:40px;">
                                        下午
                                    </span>

                                    <select id="end_hour" class="form-select">
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                        <option value="6">6</option>
                                        <option value="7">7</option>
                                    </select>

                                    <span>：</span>

                                    <select id="end_minute" class="form-select">
                                        <option value="00">00</option>
                                        <option value="30">30</option>
                                    </select>

                                </div>

                                <input
                                    type="hidden"
                                    name="shift_end"
                                    id="shift_end">
                            </div>

                            <!-- Tolerance -->
                            <div class="col-md-3">

                                <label class="form-label">
                                    遲到容忍（分鐘）
                                </label>

                                <input
                                    type="number"
                                    class="form-control"
                                    name="late_tolerance"
                                    id="shift_tolerance"
                                    min="0"
                                    max="60">

                            </div>

                            <!-- Working Hours -->
                            <div class="col-md-3">

                                <label class="form-label">
                                    每日工時
                                </label>

                                <div
                                    id="working_hours"
                                    class="form-control bg-light fw-semibold text-primary d-flex align-items-center">

                                    共 8 小時

                                </div>

                            </div>

                            {{-- single alert only --}}
                            <div class="col-12">
                                <div class="alert alert-info d-flex align-items-center mb-0 py-2">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <small>午休 12:00–13:00 固定扣除，所有部門一致。</small>
                                </div>
                            </div>

                        </div>  {{-- closes row g-3 --}}
                        <div class="mt-3">
                            <button type="submit" class="btn btn-navy">
                                <i class="bi bi-check-lg me-1"></i>儲存班次
                            </button>
                        </div>
                    </form>
                </div>  {{-- closes shift section div --}}
            </div>  {{-- closes modal-body --}}

                <div class="modal-footer">
                    <button type="button" class="btn btn-light"
                        data-bs-dismiss="modal">關閉</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')

<script>

function updateWorkingHours(){

    const startHour =
        parseInt(document.getElementById('start_hour').value);

    const startMinute =
        parseInt(document.getElementById('start_minute').value);

    const endHour =
        parseInt(document.getElementById('end_hour').value)+12;

    const endMinute =
        parseInt(document.getElementById('end_minute').value);

    document.getElementById('shift_start').value =
        String(startHour).padStart(2,'0')
        + ':'
        + String(startMinute).padStart(2,'0');

    document.getElementById('shift_end').value =
        String(endHour).padStart(2,'0')
        + ':'
        + String(endMinute).padStart(2,'0');

    let start =
        startHour*60+startMinute;

    let end =
        endHour*60+endMinute;

    let total=end-start;

    // Lunch 12~13
    if(start<720 && end>780){
        total-=60;
    }

    total=Math.max(total,0);

    const h=Math.floor(total/60);

    const m=total%60;

    let text="共 "+h+" 小時";

    if(m>0){
        text+=" "+m+" 分";
    }

    const card=document.getElementById('working_hours');

    card.innerHTML=text;

    card.className=
        "form-control fw-semibold d-flex align-items-center";

    if(h<=8){

        card.classList.add("text-success","bg-light");

    }else if(h==9){

        card.classList.add("text-warning","bg-light");

    }else{

        card.classList.add("text-danger","bg-light");

    }

}


function openEditModal(dept,shiftStart,shiftEnd,tolerance){

    document.getElementById('modal_dept_label').textContent=dept;

    document.getElementById('rename_old_name').value=dept;

    document.getElementById('rename_new_name').value=dept;

    document.getElementById('shift_department').value=dept;

    const[startHour24,startMinute]=shiftStart.split(':');

    document.getElementById('start_hour').value=
        parseInt(startHour24);

    document.getElementById('start_minute').value=
        startMinute;

    const[endHour24,endMinute]=shiftEnd.split(':');

    document.getElementById('end_hour').value=
        parseInt(endHour24)-12;

    document.getElementById('end_minute').value=
        endMinute;

    document.getElementById('shift_tolerance').value=
        tolerance;

    updateWorkingHours();

    new bootstrap.Modal(
        document.getElementById('editModal')
    ).show();

}


document.querySelectorAll(

    '#start_hour,#start_minute,#end_hour,#end_minute'

).forEach(function(el){

    el.addEventListener(

        'change',

        updateWorkingHours

    );

});


document.getElementById('shiftForm')

.addEventListener('submit',function(){

    updateWorkingHours();

});

</script>

@endsection