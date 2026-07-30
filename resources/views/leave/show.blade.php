@extends('layouts.app')
@section('page-title', '請假申請詳情')

@section('content')
@php
    $status    = $leave->status instanceof \App\Enums\LeaveStatus ? $leave->status->value : $leave->status;
    $leaveType = $leave->leave_type instanceof \App\Enums\LeaveType ? $leave->leave_type->value : $leave->leave_type;
@endphp

<div class="mb-3">
    <a href="{{ route('leave.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> 返回清單
    </a>
</div>

<div class="row g-4">

    {{-- Info card --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-file-text me-2"></i>申請資料
                </span>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tbody>
                        @foreach([
                            '申請人'     => $leave->employee->name.' ('.$leave->employee->employee_no.')',
                            '假別'       => $leaveType,
                            '開始日期'   => $leave->start_date->format('Y/m/d'),
                            '結束日期'   => $leave->end_date->format('Y/m/d'),
                            '請假時數/天數' => $leave->hours
                                ? $leave->hours.' 小時（已扣除午休）'.($leave->start_time && $leave->end_time ? '，時段：'.$leave->start_time.' – '.$leave->end_time : '')
                                : $leave->days.' 個工作天',
                            '事由'       => $leave->leave_reason ?? '—',
                            '送出時間'   => $leave->created_at->format('Y/m/d H:i'),
                        ] as $label => $value)
                        <tr>
                            <td class="text-muted fw-semibold" style="width:120px;">{{ $label }}</td>
                            <td class="fw-semibold">{{ $value }}</td>
                        </tr>
                        @endforeach
                        <tr>
                            <td class="text-muted fw-semibold">狀態</td>
                            <td><span class="badge badge-{{ $status }} px-2 py-1 fs-6">{{ $status }}</span></td>
                        </tr>
                        @if($leave->admin_note)
                        <tr>
                            <td class="text-muted fw-semibold">主管備註</td>
                            <td class="text-danger">{{ $leave->admin_note }}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>

                @if($status === '草稿')
                <div class="d-flex gap-2 mt-3">
                    <a href="{{ route('leave.edit', $leave->id) }}" class="btn btn-navy">
                        <i class="bi bi-pencil me-1"></i> 編輯草稿
                    </a>
                    <form method="POST" action="{{ route('leave.destroy', $leave->id) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger"
                            onclick="return confirm('確定刪除？')">
                            <i class="bi bi-trash me-1"></i> 刪除草稿
                        </button>
                    </form>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Timeline --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-diagram-3 me-2"></i>簽核狀態時間軸
                </span>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3">

                    {{-- Step 1: 送出 --}}
                    <div class="d-flex gap-3 align-items-start">
                        <div class="d-flex flex-column align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                style="width:32px;height:32px;background:#d1e7dd;color:#0f5132;flex-shrink:0;">
                                <i class="bi bi-check-lg"></i>
                            </div>
                            <div style="width:2px;height:40px;background:#dee2e6;margin-top:4px;"></div>
                        </div>
                        <div class="pt-1">
                            <div class="fw-semibold">申請送出</div>
                            <div class="text-muted small">{{ $leave->created_at->format('Y/m/d H:i') }}</div>
                        </div>
                    </div>

                    {{-- Step 2: 主管 --}}
                    <div class="d-flex gap-3 align-items-start">
                        <div class="d-flex flex-column align-items-center">
                            @php
                                $mgr_done = in_array($status,['已核准','已拒絕']) || $leave->current_approver === 'hr';
                                $mgr_current = $leave->current_approver === 'manager';
                            @endphp
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                style="width:32px;height:32px;flex-shrink:0;
                                background:{{ $mgr_done ? '#d1e7dd' : ($mgr_current ? '#fff3cd' : '#e9ecef') }};
                                color:{{ $mgr_done ? '#0f5132' : ($mgr_current ? '#856404' : '#aaa') }};">
                                <i class="bi bi-{{ $mgr_done ? 'check-lg' : ($mgr_current ? 'hourglass-split' : 'dash') }}"></i>
                            </div>
                            <div style="width:2px;height:40px;background:#dee2e6;margin-top:4px;"></div>
                        </div>
                        <div class="pt-1">
                            <div class="fw-semibold">部門主管簽核</div>
                            <div class="text-muted small">
                                @if($mgr_current) <span class="text-warning">審核中</span>
                                @elseif($mgr_done) 已完成
                                @elseif($status === '草稿') 待送出
                                @else 待審核
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Step 3: 人資 --}}
                    <div class="d-flex gap-3 align-items-start">
                        <div class="d-flex flex-column align-items-center">
                            @php
                                $hr_skip    = $leave->days <= 3;
                                $hr_done    = !$hr_skip && $status === '已核准';
                                $hr_current = $leave->current_approver === 'hr';
                                $hr_rejected = !$hr_skip && $status === '已拒絕' && !$mgr_current;
                            @endphp
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                style="width:32px;height:32px;flex-shrink:0;
                                background:{{ $hr_skip ? '#e9ecef' : ($hr_done ? '#d1e7dd' : ($hr_current ? '#fff3cd' : '#e9ecef')) }};
                                color:{{ $hr_skip ? '#aaa' : ($hr_done ? '#0f5132' : ($hr_current ? '#856404' : '#aaa')) }};">
                                <i class="bi bi-{{ $hr_skip ? 'dash' : ($hr_done ? 'check-lg' : ($hr_current ? 'hourglass-split' : 'dash')) }}"></i>
                            </div>
                            <div style="width:2px;height:40px;background:#dee2e6;margin-top:4px;"></div>
                        </div>
                        <div class="pt-1">
                            <div class="fw-semibold">人資部簽核</div>
                            <div class="text-muted small">
                                @if($hr_skip) <span class="text-muted">3天以內不需要</span>
                                @elseif($hr_current) <span class="text-warning">審核中</span>
                                @elseif($hr_done) 已完成
                                @else 待前一步完成
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Step 4: 結果 --}}
                    <div class="d-flex gap-3 align-items-start">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                            style="width:32px;height:32px;flex-shrink:0;
                            background:{{ $status === '已核准' ? '#d1e7dd' : ($status === '已拒絕' ? '#f8d7da' : '#e9ecef') }};
                            color:{{ $status === '已核准' ? '#0f5132' : ($status === '已拒絕' ? '#842029' : '#aaa') }};">
                            <i class="bi bi-{{ $status === '已核准' ? 'check-circle' : ($status === '已拒絕' ? 'x-circle' : 'dash') }}"></i>
                        </div>
                        <div class="pt-1">
                            <div class="fw-semibold">最終結果</div>
                            <div class="small">
                                @if($status === '已核准') <span class="text-success fw-bold">已核准</span>
                                @elseif($status === '已拒絕') <span class="text-danger fw-bold">已拒絕</span>
                                @else <span class="text-muted">待定</span>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>
@endsection