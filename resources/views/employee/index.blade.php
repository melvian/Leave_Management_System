@extends('layouts.app')
@section('page-title', '員工管理')

@section('content')
@php
    $myRole = Auth::user()->role instanceof \App\Enums\Role
        ? Auth::user()->role->value : Auth::user()->role;
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <form method="GET" action="{{ route('employee.index') }}"
        class="d-flex gap-2 flex-wrap">
        <input type="text" name="search" value="{{ request('search') }}"
            class="form-control" style="width:200px;" placeholder="搜尋姓名...">
        <select name="dept" class="form-select" style="width:160px;">
            <option value="">全部部門</option>
            @foreach($departments as $dept)
                <option value="{{ $dept }}" {{ request('dept') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
            @endforeach
        </select>
        <select name="role" class="form-select" style="width:140px;">
            <option value="">全部角色</option>
            @foreach(['員工','部門主管','人資部','系統管理者'] as $r)
                <option value="{{ $r }}" {{ request('role') === $r ? 'selected' : '' }}>{{ $r }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-navy">
            <i class="bi bi-search me-1"></i> 搜尋
        </button>
        <a href="{{ route('employee.index') }}" class="btn btn-outline-secondary">重設</a>
    </form>
    @if(in_array($myRole, ['人資部','系統管理者']) || ($myRole === '部門主管' && Auth::user()->department === '人資部'))
    <a href="{{ route('employee.create') }}" class="btn btn-navy">
        <i class="bi bi-person-plus me-1"></i> 新增員工
    </a>
    @endif
</div>

<div class="card">
    <div class="card-body p-0">
        @if($employees->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-people fs-1 d-block mb-2"></i>
                找不到符合條件的員工
            </div>
        @else
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>員工編號</th>
                    <th>姓名</th>
                    <th>部門</th>
                    <th>角色</th>
                    <th>到職日期</th>
                    <th>年資</th>
                    <th>特休餘額</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach($employees as $emp)
                @php
                    $role = $emp->role instanceof \App\Enums\Role ? $emp->role->value : $emp->role;
                    $diff = $emp->hire_date->diff(now());
                    $roleBadgeColor = match($role) {
                        '系統管理者' => 'bg-dark',
                        '人資部'    => 'bg-info text-dark',
                        '部門主管'  => 'bg-warning text-dark',
                        default     => 'bg-secondary',
                    };
                @endphp
                <tr style="opacity:{{ $emp->is_active ? '1' : '0.55' }}">
                    <td class="fw-semibold" style="font-family:monospace;">{{ $emp->employee_no }}</td>
                    <td class="fw-semibold">{{ $emp->name }}</td>
                    <td>{{ $emp->department }}</td>
                    <td><span class="badge {{ $roleBadgeColor }}">{{ $role }}</span></td>
                    <td>{{ $emp->hire_date->format('Y/m/d') }}</td>
                    <td>{{ $diff->y }}年{{ $diff->m }}月</td>
                    <td>{{ $emp->remainingAnnualLeave() }} 天</td>
                    <td>
                        <span class="badge {{ $emp->is_active ? 'bg-success' : 'bg-secondary' }}">
                            {{ $emp->is_active ? '啟用' : '停用' }}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <a href="{{ route('employee.show', $emp->id) }}"
                                class="btn btn-sm btn-outline-primary">查看</a>
                            @if(in_array($myRole, ['人資部','系統管理者']) || ($myRole === '部門主管' && Auth::user()->department === '人資部'))
                            <a href="{{ route('employee.edit', $emp->id) }}"
                                class="btn btn-sm btn-outline-secondary">編輯</a>
                            @if($emp->is_active)
                            <form method="POST" action="{{ route('employee.deactivate', $emp->id) }}" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('確定停用 {{ $emp->name }} 的帳號？')">停用</button>
                            </form>
                            @endif
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-3 py-2 text-muted small border-top">
            共 {{ $employees->count() }} 位員工
        </div>
        @endif
    </div>
</div>
@endsection