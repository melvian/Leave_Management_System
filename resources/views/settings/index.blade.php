@extends('layouts.app')
@section('page-title', '系統設定')

@section('content')
<div class="row g-4">
    {{-- Password reset --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header card-header-navy py-2 px-4">
                <span style="font-size:14px;font-weight:600;">
                    <i class="bi bi-key me-2"></i>重設員工密碼
                </span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('settings.password.reset') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">員工編號 <span class="text-danger">*</span></label>
                        <input type="text" name="employee_no" class="form-control"
                            value="{{ old('employee_no') }}"
                            placeholder="例如：E00001"
                            style="font-family:monospace;">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">新密碼 <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control"
                            placeholder="至少6個字元">
                    </div>

                    <button type="submit" class="btn btn-navy w-100"
                        onclick="return confirm('確定重設此員工的密碼？')">
                        <i class="bi bi-key me-2"></i>重設密碼
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function showRename(dept, index) {
    document.getElementById('rename_row_' + index).style.display = 'table-row';
}
function hideRename(index) {
    document.getElementById('rename_row_' + index).style.display = 'none';
}
function openShiftModal(dept, start, end, tolerance) {
    document.getElementById('shift_dept').value     = dept;
    document.getElementById('shiftDeptLabel').textContent = dept;
    document.getElementById('shift_start').value    = start;
    document.getElementById('shift_end').value      = end;
    document.getElementById('shift_tolerance').value = tolerance;
    new bootstrap.Modal(document.getElementById('shiftModal')).show();
}
</script>
@endsection