@extends('layouts.app')
@section('page-title', '加班確認佇列')

@section('content')
<div class="d-flex gap-2 mb-4">
    <a href="{{ route('approval.leave.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-clipboard-check me-1"></i> 請假審核
    </a>
    <a href="{{ route('approval.overtime.index') }}" class="btn btn-teal">
        <i class="bi bi-check2-square me-1"></i> 加班確認
    </a>
</div>

<div class="d-flex gap-2 mb-4">
    <a href="{{ route('approval.overtime.index', ['status'=>'pending']) }}"
        class="btn btn-sm {{ $status === 'pending' ? 'btn-teal' : 'btn-outline-secondary' }}">
        待確認
    </a>
    <a href="{{ route('approval.overtime.index', ['status'=>'processed']) }}"
        class="btn btn-sm {{ $status === 'processed' ? 'btn-teal' : 'btn-outline-secondary' }}">
        已處理
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        @if($records->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                目前沒有待確認的加班記錄
            </div>
        @else
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>員工</th>
                    <th>部門</th>
                    <th>加班日期</th>
                    <th>時段</th>
                    <th>時數</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                @php
                    $recStatus = $record->status instanceof \App\Enums\OvertimeStatus
                        ? $record->status->value : $record->status;
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $record->employee->name }}</td>
                    <td>{{ $record->employee->department }}</td>
                    <td>{{ $record->date->format('Y/m/d') }}</td>
                    <td>{{ $record->start_time }} – {{ $record->end_time }}</td>
                    <td>{{ $record->hours }} 小時</td>
                    <td><span class="badge badge-{{ $recStatus }} px-2 py-1">{{ $recStatus }}</span></td>
                    <td>
                        <a href="{{ route('approval.overtime.show', $record->id) }}"
                            class="btn btn-sm btn-teal">審核</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection