@extends('layouts.app')
@section('page-title', '新增員工')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-person-plus me-2"></i>建立新員工帳號
                </span>
            </div>

            <div class="card-body p-4">
                <form method="POST" action="{{ route('employee.store') }}">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                員工編號 <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                name="employee_no"
                                class="form-control"
                                value="{{ old('employee_no') }}"
                                placeholder="例如：E00011"
                                style="font-family:monospace;"
                            >
                            <div class="form-text">
                                E=員工 M=主管 H=人資 A=管理者 + 5位數字
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                姓名 <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                name="name"
                                class="form-control"
                                value="{{ old('name') }}"
                                placeholder="請輸入姓名"
                            >
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                性別 <span class="text-danger">*</span>
                            </label>

                            <select name="gender" class="form-select">
                                <option value="">— 請選擇 —</option>
                                <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>男</option>
                                <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>女</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                到職日期 <span class="text-danger">*</span>
                            </label>

                            <input
                                type="date"
                                name="hire_date"
                                class="form-control"
                                value="{{ old('hire_date') }}"
                                max="{{ date('Y-m-d') }}"
                            >
                        </div>
                    </div>

                    <div class="row g-3 mb-3">

                        {{-- Department --}}
                        <div class="col-md-6">
                            <label class="form-label">
                                部門 <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="department"
                                class="form-control"
                                value="{{ old('department') }}"
                                list="dept_options"
                                placeholder="選擇或輸入新部門名稱"
                            >

                            <datalist id="dept_options">
                                @foreach($departments as $dept)
                                    <option value="{{ $dept }}">
                                @endforeach
                            </datalist>

                            <div class="form-text">
                                可從下拉選擇現有部門，或直接輸入新部門名稱。
                            </div>
                        </div>

                        {{-- Role --}}
                        <div class="col-md-6">
                            <label class="form-label">
                                角色 <span class="text-danger">*</span>
                            </label>

                            <select name="role" class="form-select">
                                <option value="">— 請選擇 —</option>

                                @foreach(['員工','部門主管','人資部','系統管理者'] as $r)
                                    <option value="{{ $r }}"
                                        {{ old('role') === $r ? 'selected' : '' }}>
                                        {{ $r }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>

                    <div class="mb-4">
                        <label class="form-label">
                            初始密碼 <span class="text-danger">*</span>
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            placeholder="至少6個字元"
                        >
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-navy">
                            <i class="bi bi-person-check me-1"></i>
                            建立員工帳號
                        </button>

                        <a href="{{ route('employee.index') }}" class="btn btn-light ms-auto">
                            取消
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
@endsection