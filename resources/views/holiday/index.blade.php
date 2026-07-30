@extends('layouts.app')
@section('page-title', '假日管理')

@section('content')
<div class="row g-4">

    {{-- Add holiday form --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-plus-circle me-2"></i>新增假日
                </span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('holidays.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">假日名稱 <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                            value="{{ old('name') }}"
                            placeholder="例如：颱風假、中秋節">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">類型 <span class="text-danger">*</span></label>
                        <select name="type" class="form-select">
                            <option value="public"  {{ old('type')==='public'  ? 'selected':'' }}>國定假日</option>
                            <option value="typhoon" {{ old('type')==='typhoon' ? 'selected':'' }}>颱風假</option>
                            <option value="other"   {{ old('type')==='other'   ? 'selected':'' }}>特別假日</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">開始日期 <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control"
                                value="{{ old('start_date') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label">結束日期 <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control"
                                value="{{ old('end_date') }}">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">備註</label>
                        <input type="text" name="note" class="form-control"
                            value="{{ old('note') }}"
                            placeholder="選填">
                    </div>

                    <button type="submit" class="btn btn-navy w-100">
                        <i class="bi bi-plus-circle me-1"></i>新增假日
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Holiday list --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-calendar-x me-2"></i>假日清單
                    <span class="ms-2 text-white-50" style="font-size:12px;">
                        共 {{ $holidays->count() }} 筆
                    </span>
                </span>
            </div>
            <div class="card-body p-0">
                @if($holidays->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar fs-1 d-block mb-2"></i>
                        尚未新增任何假日
                    </div>
                @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>假日名稱</th>
                            <th>類型</th>
                            <th>日期範圍</th>
                            <th>天數</th>
                            <th>備註</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($holidays as $holiday)
                        @php
                            $days = $holiday->start_date->diffInDays($holiday->end_date) + 1;
                            $typeColor = match($holiday->type) {
                                'typhoon' => 'bg-primary',
                                'public'  => 'bg-success',
                                default   => 'bg-secondary',
                            };
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $holiday->name }}</td>
                            <td>
                                <span class="badge {{ $typeColor }}">
                                    {{ $holiday->typeLabel() }}
                                </span>
                            </td>
                            <td>
                                {{ $holiday->start_date->format('Y/m/d') }}
                                @if($holiday->start_date != $holiday->end_date)
                                    ~ {{ $holiday->end_date->format('Y/m/d') }}
                                @endif
                            </td>
                            <td>{{ $days }} 天</td>
                            <td class="text-muted">{{ $holiday->note ?? '—' }}</td>
                            <td>
                                <form method="POST"
                                    action="{{ route('holidays.destroy', $holiday->id) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('確定刪除「{{ $holiday->name }}」？')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection