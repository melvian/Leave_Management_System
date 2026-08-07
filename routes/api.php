<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EmployeeApiController;
use App\Http\Controllers\Api\LeaveRequestApiController;
use App\Http\Controllers\Api\OvertimeRecordApiController;

Route::get('/employees',        [EmployeeApiController::class, 'index']);
Route::get('/employees/{id}',   [EmployeeApiController::class, 'show']);
Route::post('/employees',       [EmployeeApiController::class, 'store']);
Route::put('/employees/{id}',   [EmployeeApiController::class, 'update']);
Route::delete('/employees/{id}',[EmployeeApiController::class, 'destroy']);

Route::get('/leave-requests',        [LeaveRequestApiController::class, 'index']);
Route::get('/leave-requests/{id}',   [LeaveRequestApiController::class, 'show']);
Route::post('/leave-requests',       [LeaveRequestApiController::class, 'store']);
Route::put('/leave-requests/{id}',   [LeaveRequestApiController::class, 'update']);
Route::delete('/leave-requests/{id}',[LeaveRequestApiController::class, 'destroy']);

Route::get('/overtime-records',        [OvertimeRecordApiController::class, 'index']);
Route::get('/overtime-records/{id}',   [OvertimeRecordApiController::class, 'show']);
Route::post('/overtime-records',       [OvertimeRecordApiController::class, 'store']);
Route::put('/overtime-records/{id}',   [OvertimeRecordApiController::class, 'update']);
Route::delete('/overtime-records/{id}',[OvertimeRecordApiController::class, 'destroy']);

// Line Attendance
Route::get('/line/user-id',   [App\Http\Controllers\Api\LineController::class, 'getUserId']);
Route::post('/line/clock-in', [App\Http\Controllers\Api\LineController::class, 'clockIn']);
Route::post('/line/clock-out',[App\Http\Controllers\Api\LineController::class, 'clockOut']);

// Line Leave
Route::get('/line/balance',       [App\Http\Controllers\Api\LineController::class, 'balance']);
Route::get('/line/my-leaves',     [App\Http\Controllers\Api\LineController::class, 'myLeaves']);
Route::get('/line/hr',            [App\Http\Controllers\Api\LineController::class, 'getHrLineId']);
Route::get('/line/manager',       [App\Http\Controllers\Api\LineController::class, 'getManagerLineId']);
Route::get('/line/pending-leaves',[App\Http\Controllers\Api\LineController::class, 'PendingLeaves']);
Route::post('/line/leave-submit', [App\Http\Controllers\Api\LineController::class, 'lineLeaveSubmit']);
Route::post('/line/leave-approve',[App\Http\Controllers\Api\LineController::class, 'lineApprove']);
Route::post('/line/leave-reject', [App\Http\Controllers\Api\LineController::class, 'lineReject']);

// Line Overtime
Route::get('/line/my-overtime',      [App\Http\Controllers\Api\LineController::class, 'myOvertime']);
Route::get('/line/pending-overtime', [App\Http\Controllers\Api\LineController::class, 'PendingOvertime']);
Route::post('/line/overtime-submit', [App\Http\Controllers\Api\LineController::class, 'lineOvertimeSubmit']); 
Route::post('/line/overtime-confirm',[App\Http\Controllers\Api\LineController::class, 'lineOvertimeConfirm']);
Route::post('/line/overtime-reject', [App\Http\Controllers\Api\LineController::class, 'lineOvertimeReject']);

// Line Delegation
Route::get('/line/my-profile',        [App\Http\Controllers\Api\LineController::class, 'myProfile']);
Route::get('/line/my-delegations',     [App\Http\Controllers\Api\LineController::class, 'myDelegations']);
Route::get('/line/employee-by-no',    [App\Http\Controllers\Api\LineController::class, 'employeeByNo']);
Route::post('/line/delegation-set',   [App\Http\Controllers\Api\LineController::class, 'lineSetDelegation']);
Route::post('/line/delegation-revoke',[App\Http\Controllers\Api\LineController::class, 'lineRevokeDelegation']);
