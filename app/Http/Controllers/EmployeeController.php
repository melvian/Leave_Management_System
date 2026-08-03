<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\Employee;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::query();
        if ($request->filled('dept'))   $query->where('department', $request->dept);
        if ($request->filled('role'))   $query->where('role', $request->role);
        if ($request->filled('search')) $query->where('name', 'like', "%{$request->search}%");

        $employees   = $query->orderBy('name')->get();
        $departments = Employee::distinct()->pluck('department');
        return view('employee.index', compact('employees', 'departments'));
    }

    public function create()
    {
        $departments = Employee::distinct()->pluck('department');
        return view('employee.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_no' => 'required|unique:employees',
            'name'        => 'required',
            'gender'      => 'required|in:male,female',
            'hire_date'   => 'required|date',
            'department'  => 'required',
            'role'        => 'required',
            'password'    => 'required|min:6',
        ]);

        if ($request->role === '部門主管') {
            $existingManager = Employee::where('department', $request->department)
                ->where('role', '部門主管')
                ->where('is_active', true)
                ->exists();
            if ($existingManager) {
                return back()
                    ->withErrors(['role' => "「{$request->department}」已有部門主管，每個部門只能設定一位主管。"])
                    ->withInput();
            }
        }

        if ($request->role === '系統管理者' && $request->department !== '數位發展部') {
            return back()
                ->withErrors(['role' => '系統管理者角色僅限數位發展部的員工。'])
                ->withInput();
        }

        if ($request->role === '人資部' && $request->department !== '人資部') {
            return back()
                ->withErrors(['role' => '人資部角色僅限人資部的員工。'])
                ->withInput();
        }

        Employee::create([
            'employee_no' => $request->employee_no,
            'name'        => $request->name,
            'gender'      => $request->gender,
            'hire_date'   => $request->hire_date,
            'department'  => $request->department,
            'role'        => $request->role,
            'password'    => Hash::make($request->password),
            'is_active'   => 1,
            'compensatory_hours_remaining' => 0,
        ]);

        return redirect()->route('employee.index')->with('success', '員工帳號已建立。');
    }

    public function show($id)
    {
        $emp          = Employee::findOrFail($id);
        $leaveHistory = $emp->leaveRequests()->orderBy('created_at', 'desc')->get();
        $otHistory    = $emp->overtimeRecords()->orderBy('date', 'desc')->get();
        $myRole       = Auth::user()->role instanceof \App\Enums\Role
                        ? Auth::user()->role->value
                        : Auth::user()->role;
        return view('employee.show', compact('emp', 'leaveHistory', 'otHistory'));
    }

    public function edit($id)
    {
        $emp         = Employee::findOrFail($id);
        $departments = Employee::distinct()->pluck('department');
        $myRole      = Auth::user()->role instanceof \App\Enums\Role
                        ? Auth::user()->role->value
                        : Auth::user()->role;
        return view('employee.edit', compact('emp', 'departments','myRole'));
    }

    public function update(Request $request, $id)
    {
        $emp = Employee::findOrFail($id);

        $request->validate([
            'name'       => 'required|string|max:50',
            'department' => 'required|string',
            'role'       => 'required|string',
            'line_user_id' => 'nullable|string|max:100',
        ]);

        // ── Role validation checks ─────────────────────────────

        // 1. Prevent two 部門主管 in same department
        if ($request->role === '部門主管') {
            $existingManager = Employee::where('department', $request->department)
                ->where('role', '部門主管')
                ->where('id', '!=', $id)
                ->where('is_active', true)
                ->exists();

            if ($existingManager) {
                return back()
                    ->withErrors(['role' => "「{$request->department}」已有部門主管，每個部門只能設定一位主管。如需更換，請先將現任主管的角色調整為員工。"])
                    ->withInput();
            }
        }

        // 2. 系統管理者 can only be from 數位發展部
        if ($request->role === '系統管理者' && $request->department !== '數位發展部') {
            return back()
                ->withErrors(['role' => '系統管理者角色僅限數位發展部的員工。'])
                ->withInput();
        }

        // 3. 人資部 role can only be from 人資部
        if ($request->role === '人資部' && $request->department !== '人資部') {
            return back()
                ->withErrors(['role' => '人資部角色僅限人資部的員工。'])
                ->withInput();
        }

        // ── Save ────────────────────────────────────────────────
        $emp->update([
            'name'       => $request->name,
            'department' => $request->department,
            'role'       => $request->role,
            'line_user_id' => $request->line_user_id ?: null,
        ]);

        return redirect()->route('employee.show', $id)
            ->with('success', '員工資料已更新。');
    }

    public function deactivate($id)
    {
        $emp = Employee::findOrFail($id);
        $emp->update(['is_active' => 0]);
        return redirect()->route('employee.index')->with('success', '員工帳號已停用。');
    }

    public function resetPassword(Request $request, $id)
    {
        $request->validate([
            'new_password' => 'required|min:6|confirmed',
        ], [
            'new_password.required'  => '新密碼為必填。',
            'new_password.min'       => '密碼至少需要6個字元。',
            'new_password.confirmed' => '兩次密碼輸入不一致。',
        ]);

        $emp = Employee::findOrFail($id);
        $emp->update(['password' => Hash::make($request->new_password)]);

        return redirect()->route('employee.show', $id)
            ->with('success', "員工 {$emp->name} 的密碼已重設。");
    }
}