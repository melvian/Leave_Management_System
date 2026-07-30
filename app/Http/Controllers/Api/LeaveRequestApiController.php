<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class LeaveRequestApiController extends Controller
{
    // GET /api/leave-requests
    public function index()
    {
        return response()->json(LeaveRequest::all(), 200);
    }

    // GET /api/leave-requests/{id}
    public function show($id)
    {
        $leave = LeaveRequest::find($id);

        if (!$leave) {
            return response()->json([
                'message' => 'Leave request not found.'
            ], 404);
        }

        return response()->json($leave, 200);
    }

    // POST /api/leave-requests
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'leave_type'   => 'required',
            'leave_reason' => 'required',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date',
            'days'         => 'required|numeric',
            'status'       => 'required',
        ]);

        $status = $validated['status'];

        $currentApprover = null;

        if ($status === '簽核中') {
            $currentApprover = 'manager';
        }

        $leave = LeaveRequest::create([
            'employee_id'      => $validated['employee_id'],
            'leave_type'       => $validated['leave_type'],
            'leave_reason'     => $validated['leave_reason'],
            'start_date'       => $validated['start_date'],
            'end_date'         => $validated['end_date'],
            'days'             => $validated['days'],
            'status'           => $status,
            'current_approver' => $currentApprover,
        ]);

        return response()->json($leave, 201);
    }

    // PUT /api/leave-requests/{id}
    public function update(Request $request, $id)
    {
        $leave = LeaveRequest::find($id);

        if (!$leave) {
            return response()->json([
                'message' => 'Leave request not found.'
            ], 404);
        }

        $leave->update($request->all());

        return response()->json($leave, 200);
    }

    // DELETE /api/leave-requests/{id}
    public function destroy($id)
    {
        $leave = LeaveRequest::find($id);

        if (!$leave) {
            return response()->json([
                'message' => 'Leave request not found.'
            ], 404);
        }

        $leave->delete();

        return response()->json([
            'message' => 'Leave request deleted successfully.'
        ], 200);
    }
}