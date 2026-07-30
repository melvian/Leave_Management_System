@extends('layouts.app')
@section('page-title', '請假審核佇列')

@section('content')
<div class="d-flex gap-2 mb-4">
    <a href="{{ route('approval.leave.index') }}" class="btn btn-navy">
        <i class="bi bi-clipboard-check me-1"></i> 請假審核
    </a>
    <a href="{{ route('approval.overtime.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-check2-square me-1"></i> 加班確認
    </a>
</div>

<div class="d-flex gap-2 mb-4">
    <a href="{{ route('approval.leave.index', ['status'=>'pending']) }}"
        class="btn btn-sm {{ $status === 'pending' ? 'btn-navy' : 'btn-outline-secondary' }}">
        待我審核
    </a>
    <a href="{{ route('approval.leave.index', ['status'=>'processed']) }}"
        class="btn btn-sm {{ $status === 'processed' ? 'btn-navy' : 'btn-outline-secondary' }}">
        已處理
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        @if($leaves->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                目前沒有待審核的申請
            </div>
        @else
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>員工</th>
                    <th>部門</th>
                    <th>假別</th>
                    <th>日期範圍</th>
                    <th>天數</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach($leaves as $leave)
                @php
                    $lt = $leave->leave_type instanceof \App\Enums\LeaveType ? $leave->leave_type->value : $leave->leave_type;
                    $ls = $leave->status instanceof \App\Enums\LeaveStatus ? $leave->status->value : $leave->status;
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $leave->employee->name }}</td>
                    <td>{{ $leave->employee->department }}</td>
                    <td>{{ $lt }}</td>
                    <td>{{ $leave->start_date->format('Y/m/d') }} ~ {{ $leave->end_date->format('Y/m/d') }}</td>
                    <td>{{ $leave->hours ? $leave->hours.'小時' : $leave->days.'天' }}</td>
                    <td><span class="badge badge-{{ $ls }}">{{ $ls }}</span></td>
                    <td>
                        <a href="{{ route('approval.leave.show', $leave->id) }}"
                            class="btn btn-sm btn-navy">審核</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection