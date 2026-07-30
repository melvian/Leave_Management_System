<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DelegationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\HolidayController;

// ── Public routes (guest only) ───────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// ── All authenticated users ───────────────────────────────
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/', fn() => redirect()->route('dashboard'));

    // Leave Requests — all logged-in users
    Route::prefix('leave-requests')->name('leave.')->group(function () {
        Route::get('/',          [LeaveController::class, 'index'])->name('index');
        Route::get('/create',    [LeaveController::class, 'create'])->name('create');
        Route::post('/',         [LeaveController::class, 'store'])->name('store');
        Route::get('/{id}',      [LeaveController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [LeaveController::class, 'edit'])->name('edit');
        Route::put('/{id}',      [LeaveController::class, 'update'])->name('update');
        Route::delete('/{id}',   [LeaveController::class, 'destroy'])->name('destroy');
    });

    // Overtime Records — all logged-in users
    Route::prefix('overtime-records')->name('overtime.')->group(function () {
        Route::get('/',         [OvertimeController::class, 'index'])->name('index');
        Route::get('/create',   [OvertimeController::class, 'create'])->name('create');
        Route::post('/',        [OvertimeController::class, 'store'])->name('store');
        Route::get('/{id}',     [OvertimeController::class, 'show'])->name('show');
    });

});

// ── Manager + HR + Admin only ─────────────────────────────
Route::middleware(['auth', 'role:部門主管,人資部,系統管理者'])
    ->prefix('approvals')->name('approval.')->group(function () {
    Route::get('/leave',                       [ApprovalController::class, 'leaveIndex'])->name('leave.index');
    Route::get('/leave/{id}',                  [ApprovalController::class, 'leaveShow'])->name('leave.show');
    Route::post('/leave/{id}/approve',         [ApprovalController::class, 'leaveApprove'])->name('leave.approve');
    Route::post('/leave/{id}/reject',          [ApprovalController::class, 'leaveReject'])->name('leave.reject');
    Route::get('/overtime',                    [ApprovalController::class, 'overtimeIndex'])->name('overtime.index');
    Route::get('/overtime/{id}',               [ApprovalController::class, 'overtimeShow'])->name('overtime.show');
    Route::post('/overtime/{id}/confirm',      [ApprovalController::class, 'overtimeConfirm'])->name('overtime.confirm');
    Route::post('/overtime/{id}/reject',       [ApprovalController::class, 'overtimeReject'])->name('overtime.reject');
});

// Delegations — Admin/HR can manage anyone, managers can manage their own
Route::middleware(['auth'])->prefix('delegations')->name('delegations.')->group(function () {
    Route::post('/',              [DelegationController::class, 'store'])->name('store');
    Route::post('/{id}/deactivate', [DelegationController::class, 'deactivate'])->name('deactivate');
});

// ── HR + Admin only ───────────────────────────────────────
Route::middleware(['auth', 'role:人資部,系統管理者'])
    ->prefix('employees')->name('employee.')->group(function () {
    Route::get('/',                 [EmployeeController::class, 'index'])->name('index');
    Route::get('/create',           [EmployeeController::class, 'create'])->name('create');
    Route::post('/',                [EmployeeController::class, 'store'])->name('store');
    Route::get('/{id}',             [EmployeeController::class, 'show'])->name('show');
    Route::get('/{id}/edit',        [EmployeeController::class, 'edit'])->name('edit');
    Route::put('/{id}',             [EmployeeController::class, 'update'])->name('update');
    Route::post('/{id}/deactivate', [EmployeeController::class, 'deactivate'])->name('deactivate');
    Route::post('/{id}/reset-password', [EmployeeController::class, 'resetPassword'])->name('resetPassword');
});

// ── Department Management ───────────────────
Route::middleware(['auth', 'role:人資部,系統管理者'])
    ->prefix('departments')->name('departments.')->group(function () {
    Route::get('/',        [DepartmentController::class, 'index'])->name('index');
    Route::post('/rename', [DepartmentController::class, 'update'])->name('update');
    Route::post('/delete', [DepartmentController::class, 'destroy'])->name('destroy');
});

// ── Admin only ────────────────────────────────────────────
Route::middleware(['auth', 'role:系統管理者'])
    ->prefix('settings')->name('settings.')->group(function () {
    Route::get('/',                      [SettingsController::class, 'index'])->name('index');
    Route::post('/departments',          [SettingsController::class, 'storeDepartment'])->name('departments.store');
    Route::post('/departments/rename',   [SettingsController::class, 'updateDepartment'])->name('departments.update');
    Route::delete('/departments/{id}',   [SettingsController::class, 'destroyDepartment'])->name('departments.destroy');
    Route::post('/password-reset',       [SettingsController::class, 'resetPassword'])->name('password.reset');
    Route::post('/shifts', [SettingsController::class,'updateShif'])->name('shifts.update');
});

// Attendance — all logged-in users
Route::prefix('attendance')->name('attendance.')->group(function () {
    Route::get('/',           [AttendanceController::class, 'index'])->name('index');
    Route::post('/clock-in',  [AttendanceController::class, 'clockIn'])->name('clock-in');
    Route::post('/clock-out', [AttendanceController::class, 'clockOut'])->name('clock-out');
});

// Attendance management — HR + Admin only
Route::middleware(['auth', 'role:人資部,系統管理者'])
    ->prefix('attendance-management')->name('attendance.management.')->group(function () {
    Route::get('/', [AttendanceController::class, 'management'])->name('index');
    Route::get('/export',  [AttendanceController::class, 'export'])->name('export');
    Route::post('/correct',[AttendanceController::class, 'correct'])->name('correct');
});

Route::middleware(['auth', 'role:人資部,系統管理者'])
    ->prefix('holidays')->name('holidays.')->group(function () {
    Route::get('/',        [HolidayController::class, 'index'])->name('index');
    Route::post('/',       [HolidayController::class, 'store'])->name('store');
    Route::delete('/{id}', [HolidayController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'role:人資部,系統管理者'])
    ->prefix('departments')->name('departments.')->group(function () {
    Route::get('/',           [DepartmentController::class, 'index'])->name('index');
    Route::post('/rename',    [DepartmentController::class, 'update'])->name('update');
    Route::post('/shift',     [DepartmentController::class, 'updateShift'])->name('shift.update'); // ← add
    Route::post('/delete',    [DepartmentController::class, 'destroy'])->name('destroy');
});