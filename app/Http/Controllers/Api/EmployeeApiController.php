<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeApiController extends Controller
{
    // GET /api/employees
    public function index()
    {
        return response()->json(Employee::all(), 200);
    }

    // GET /api/employees/{id}
    public function show($id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found.'
            ], 404);
        }

        return response()->json($employee, 200);
    }

    // POST /api/employees
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_no' => 'required|unique:employees',
            'name'        => 'required',
            'gender'      => 'required',
            'department'  => 'required',
            'role'        => 'required',
            'hire_date'   => 'required|date',
            'password'    => 'required|min:6',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = 1;

        $employee = Employee::create($validated);

        return response()->json($employee, 201);
    }

    // PUT /api/employees/{id}
    public function update(Request $request, $id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found.'
            ], 404);
        }

        $employee->update($request->except('password'));

        if ($request->filled('password')) {
            $employee->password = Hash::make($request->password);
            $employee->save();
        }

        return response()->json($employee, 200);
    }

    // DELETE /api/employees/{id}
    public function destroy($id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found.'
            ], 404);
        }

        $employee->delete();

        return response()->json([
            'message' => 'Employee deleted successfully.'
        ], 200);
    }
}