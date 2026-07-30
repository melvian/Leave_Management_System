@extends('layouts.app')

@section('content')
<div class="min-vh-100 d-flex align-items-center justify-content-center" style="background:#F4F7FB;">
    <div style="width:420px;">

        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                style="width:56px;height:56px;background:#1F3864;">
                <i class="bi bi-building text-white fs-4"></i>
            </div>
            <h4 style="color:#1F3864;font-weight:700;">員工差勤暨請假管理系統</h4>
            <p class="text-muted small">大愛電視台 數位發展中心</p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-4" style="color:#1F3864;">請登入您的帳號</h6>

                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">員工編號</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="employee_no" class="form-control @error('employee_no') is-invalid @enderror"
                                value="{{ old('employee_no') }}" placeholder="例如：E00001" autofocus>
                            @error('employee_no')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">密碼</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control"
                                placeholder="請輸入密碼">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-navy w-100 py-2">
                        <i class="bi bi-box-arrow-in-right me-2"></i>登入
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-muted small mt-3">如有問題請聯繫系統管理者</p>
    </div>
</div>
@endsection