@extends('layouts.app')
@section('page-title', '編輯員工資料')

@section('content')
@php
    $empRole = $emp->role instanceof \App\Enums\Role
        ? $emp->role->value
        : $emp->role;
@endphp

<div class="row justify-content-center">
<div class="col-lg-7">

<div class="info-box mb-4">
    <strong>{{ $emp->name }}</strong>（{{ $emp->employee_no }}）
    <span class="text-muted small ms-2">到職：{{ $emp->hire_date->format('Y/m/d') }}</span>
</div>

<div class="card">
    <div class="card-header card-header-navy py-2 px-4">
        <span style="font-size:14px;font-weight:600;">
            <i class="bi bi-pencil-square me-2"></i>編輯員工資料
        </span>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="{{ route('employee.update', $emp->id) }}">
            @csrf @method('PUT')

            <div class="mb-3">
                <label class="form-label">姓名</label>
                <input type="text" name="name" class="form-control"
                    value="{{ old('name', $emp->name) }}">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">部門</label>
                    <select name="department" class="form-select">
                        @foreach($departments as $dept)
                            <option value="{{ $dept }}"
                                {{ old('department', $emp->department) === $dept ? 'selected' : '' }}>
                                {{ $dept }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">角色 <span class="text-danger">*</span></label>
                    <select name="role" id="role_select" class="form-select"
                        onchange="checkRoleRestriction()">
                        @foreach(['員工','部門主管','人資部','系統管理者'] as $r)
                            <option value="{{ $r }}" {{ old('role', $empRole) === $r ? 'selected' : '' }}>
                                {{ $r }}
                            </option>
                        @endforeach
                    </select>
                    <div id="role_warning" class="text-danger small mt-1" style="display:none;"></div>
                    <div class="form-text">
                        <i class="bi bi-info-circle me-1"></i>
                        系統管理者限數位發展部｜人資部角色限人資部｜每部門限一位主管
                    </div>
                </div>
            </div>

            <div class="alert alert-warning d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                修改角色將影響該員工的系統存取權限，請謹慎操作。
            </div>

            {{-- Line Account Binding --}}
            <div class="col-12">
                <hr class="my-2">
                <h6 class="fw-bold mb-3" style="color:#1F3864;">
                    <i class="bi bi-chat-dots me-2"></i>Line 帳號綁定
                </h6>
            </div>

            <div class="col-md-8">
                <label class="form-label">Line User ID</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:#06C755;">
                        <i class="bi bi-chat-fill text-white"></i>
                    </span>
                    <input type="text" name="line_user_id" class="form-control"
                        value="{{ old('line_user_id', $emp->line_user_id) }}"
                        placeholder="U 開頭的 Line 用戶 ID，例如：Uxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                </div>

                @if($emp->line_user_id)
                <div class="mt-2 d-flex align-items-center gap-2">
                    <span class="badge" style="background:#06C755;">
                        <i class="bi bi-check-circle me-1"></i>已綁定
                    </span>
                    <span class="text-muted" style="font-family:monospace; font-size:12px;">
                        {{ $emp->line_user_id }}
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="clearLineId()">
                        <i class="bi bi-x-circle me-1"></i>解除綁定
                    </button>
                </div>
                @else
                <div class="mt-2">
                    <span class="badge bg-secondary">
                        <i class="bi bi-dash-circle me-1"></i>未綁定
                    </span>
                </div>
                @endif

                <div class="form-text">
                    <i class="bi bi-info-circle me-1"></i>
                    員工 Line User ID 可由員工傳訊給 Line Bot 後，從伺服器日誌取得。格式為 U 開頭共34個字元。
                </div>
            </div>

            {{-- Hidden field for clearing --}}
            <input type="hidden" name="_clear_line_id" id="clear_line_id_flag" value="0">

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-navy">
                    <i class="bi bi-check-lg me-1"></i> 儲存變更
                </button>
                <a href="{{ route('employee.show', $emp->id) }}" class="btn btn-light ms-auto">取消</a>
            </div>
        </form>
    </div>
</div>

</div>
</div>

{{-- Password reset — Admin only --}}
@if($myRole === '系統管理者')
<div class="row justify-content-center mt-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-key me-2"></i>重設員工密碼
                </span>
            </div>
            <div class="card-body p-4">
                <form method="POST"
                    action="{{ route('employee.resetPassword', $emp->id) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">新密碼 <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control"
                            placeholder="至少6個字元">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">確認新密碼 <span class="text-danger">*</span></label>
                        <input type="password" name="new_password_confirmation"
                            class="form-control" placeholder="再次輸入新密碼">
                    </div>
                    <button type="submit" class="btn btn-navy w-100"
                        onclick="return confirm('確定重設 {{ $emp->name }} 的密碼？')">
                        <i class="bi bi-key me-2"></i>重設密碼
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

@section('scripts')
<script>

function clearLineId() {
    if (confirm('確定解除此員工的 Line 帳號綁定？')) {
        document.querySelector('input[name="line_user_id"]').value = '';
        document.getElementById('clear_line_id_flag').value = '1';
        document.querySelector('form').submit();
    }
}

// Role restriction check
const currentDept = "{{ $emp->department }}";
function checkRoleRestriction() {
    const role = document.getElementById('role_select').value;
    const warning = document.getElementById('role_warning');

    if (role === '系統管理者' && currentDept !== '數位發展部') {
        warning.textContent = '⚠ 系統管理者角色僅限數位發展部的員工。';
        warning.style.display = 'block';
    } else if (role === '人資部' && currentDept !== '人資部') {
        warning.textContent = '⚠ 人資部角色僅限人資部的員工。';
        warning.style.display = 'block';
    } else {
        warning.style.display = 'none';
    }
}
checkRoleRestriction();
</script>
@endsection

@endsection