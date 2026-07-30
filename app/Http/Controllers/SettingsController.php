<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Employee;

class SettingsController extends Controller
{
    public function index()
    {
        $departments = \App\Models\Employee::distinct()->pluck('department')->sort()->values();
        $deptCounts  = \App\Models\Employee::selectRaw('department, count(*) as total')
                            ->groupBy('department')
                            ->pluck('total', 'department');
        $shifts      = \App\Models\ShiftSetting::all()->keyBy('department');

        return view('settings.index', compact('departments', 'deptCounts', 'shifts'));
    }

    public function storeDepartment(Request $request)
    {
        // Departments are created automatically via 新增員工
        return redirect()->route('settings.index')
            ->with('info', '請透過「新增員工」新增部門。');
    }

    public function updateDepartment(Request $request)
    {
        $request->validate([
            'old_name' => 'required|string',
            'new_name' => 'required|string|max:50',
        ]);

        $count = \App\Models\Employee::where('department', $request->old_name)->count();

        if ($count === 0) {
            return redirect()->route('settings.index')
                ->with('error', '找不到該部門。');
        }

        \App\Models\Employee::where('department', $request->old_name)
            ->update(['department' => $request->new_name]);

        return redirect()->route('settings.index')
            ->with('success', "部門「{$request->old_name}」已更名為「{$request->new_name}」，共更新 {$count} 位員工。");
    }

    public function updateShift(Request $request)
    {
        $request->validate([
            'department'    => 'required|string',
            'shift_start'   => 'required|date_format:H:i',
            'shift_end'     => 'required|date_format:H:i|after:shift_start',
            'late_tolerance'=> 'required|integer|min:0|max:60',
        ]);

        \App\Models\ShiftSetting::updateOrCreate(
            ['department' => $request->department],
            [
                'shift_start'    => $request->shift_start,
                'shift_end'      => $request->shift_end,
                'late_tolerance' => $request->late_tolerance,
            ]
        );

        return redirect()->route('settings.index')
            ->with('success', "「{$request->department}」的班次設定已更新。");
    }

    public function destroyDepartment($id)
    {
        return redirect()->route('settings.index');
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'employee_no'  => 'required|exists:employees,employee_no',
            'new_password' => 'required|min:6',
        ]);

        $emp = Employee::where('employee_no', $request->employee_no)->firstOrFail();
        $emp->update(['password' => Hash::make($request->new_password)]);

        return redirect()->route('settings.index')
            ->with('success', "員工 {$emp->name} 的密碼已重設。");
    }
}
