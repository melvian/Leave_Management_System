@extends('layouts.app')
@section('page-title', '加班記錄詳情')

@section('content')
<div class="mb-3">
    <a href="{{ route('overtime.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> 返回清單
    </a>
</div>

@php
    $status = $record->status instanceof \App\Enums\OvertimeStatus
        ? $record->status->value : $record->status;
@endphp

<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card">
    <div class="card-header card-header-navy py-2 px-4">
        <span style="font-size:14px;font-weight:600;">
            <i class="bi bi-clock-history me-2"></i>加班記錄詳情
        </span>
    </div>
    <div class="card-body">
        <table class="table table-borderless mb-3">
            <tbody>
                @foreach([
                    '員工'     => $record->employee->name.' ('.$record->employee->employee_no.')',
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
                    <td class="text-muted fw-semibold">狀態</td>
                    <td><span class="badge badge-{{ $status }} px-2 py-1 fs-6">{{ $status }}</span></td>
                </tr>
                @if($record->admin_note)
                <tr>
                    <td class="text-muted fw-semibold">主管備註</td>
                    <td class="text-danger">{{ $record->admin_note }}</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
</div>
</div>
@endsection