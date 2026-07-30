@extends('layouts.app')
@section('page-title', '我的加班記錄')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-2">
        @foreach([''=>'全部','待確認'=>'待確認','已確認'=>'已確認','已駁回'=>'已駁回'] as $val => $label)
            <a href="{{ route('overtime.index', ['status'=>$val]) }}"
                class="btn btn-sm {{ request('status') === $val ? 'btn-teal' : 'btn-outline-secondary' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
    <a href="{{ route('overtime.create') }}" class="btn btn-teal">
        <i class="bi bi-plus-circle me-1"></i> 新增加班記錄
    </a>
</div>

<div class="info-box mb-4" style="border-left-color:#0E7C86;">
    <i class="bi bi-clock-history me-2" style="color:#0E7C86;"></i>
    補休餘額：<strong style="font-size:20px;color:#0E7C86;">{{ Auth::user()->compensatory_hours_remaining }}</strong> 小時
</div>

<div class="card">
    <div class="card-body p-0">
        @if($records->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-clock fs-1 d-block mb-2"></i>
                目前沒有加班記錄
            </div>
        @else
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>加班日期</th>
                    <th>時段</th>
                    <th>時數</th>
                    <th>加班事由</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                @php
                    $status = $record->status instanceof \App\Enums\OvertimeStatus
                        ? $record->status->value : $record->status;
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $record->date->format('Y/m/d') }}</td>
                    <td>{{ $record->start_time }} – {{ $record->end_time }}</td>
                    <td>{{ $record->hours }} 小時</td>
                    <td class="text-muted">{{ $record->overtime_reason ?? '—' }}</td>
                    <td><span class="badge badge-{{ $status }} px-2 py-1">{{ $status }}</span></td>
                    <td>
                        <a href="{{ route('overtime.show', $record->id) }}"
                            class="btn btn-sm btn-outline-primary">查看</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection