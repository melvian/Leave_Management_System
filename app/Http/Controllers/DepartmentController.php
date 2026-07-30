<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\ShiftSetting;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Employee::distinct()->pluck('department')->sort()->values();
        $deptCounts  = Employee::selectRaw('department, count(*) as total')
                            ->groupBy('department')
                            ->pluck('total', 'department');
        $shifts = ShiftSetting::all()->keyBy('department');
        return view('department.index', compact('departments', 'deptCounts', 'shifts'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'old_name' => 'required|string',
            'new_name' => 'required|string|max:50',
        ]);

        $count = Employee::where('department', $request->old_name)->count();

        if ($count === 0) {
            return redirect()->route('departments.index')
                ->with('error', '找不到該部門。');
        }

        Employee::where('department', $request->old_name)
            ->update(['department' => $request->new_name]);

        ShiftSetting::where('department', $request->old_name)
            ->update(['department' => $request->new_name]);

        return redirect()->route('departments.index')
            ->with('success', "部門「{$request->old_name}」已更名為「{$request->new_name}」，共更新 {$count} 位員工。");
    }

    public function updateShift(Request $request)
    {
        $request->validate([
            'department'     => 'required|string',
            'shift_start'    => 'required|date_format:H:i',
            'shift_end'      => 'required|date_format:H:i|after:shift_start',
            'late_tolerance' => 'required|integer|min:0|max:60',
        ]);

        ShiftSetting::updateOrCreate(
            ['department' => $request->department],
            [
                'shift_start'    => $request->shift_start,
                'shift_end'      => $request->shift_end,
                'late_tolerance' => $request->late_tolerance,
            ]
        );

        return redirect()->route('departments.index')
            ->with('success', "「{$request->department}」班次設定已更新。");
    }

    public function destroy(Request $request)
    {
        $request->validate(['name' => 'required|string']);

        $count = Employee::where('department', $request->name)->count();
        if ($count > 0) {
            return redirect()->route('departments.index')
                ->with('error', "此部門尚有 {$count} 位員工，無法刪除。請先將員工移至其他部門。");
        }

        ShiftSetting::where('department', $request->name)->delete();

        return redirect()->route('departments.index')
            ->with('success', '部門已刪除。');
    }
}
