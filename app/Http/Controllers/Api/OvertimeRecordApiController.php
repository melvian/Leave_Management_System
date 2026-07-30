<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRecord;
use Illuminate\Http\Request;

class OvertimeRecordApiController extends Controller
{
    // GET /api/overtime-records
    public function index()
    {
        return response()->json(OvertimeRecord::all(), 200);
    }

    // GET /api/overtime-records/{id}
    public function show($id)
    {
        $record = OvertimeRecord::find($id);

        if (!$record) {
            return response()->json([
                'message' => 'Overtime record not found.'
            ], 404);
        }

        return response()->json($record, 200);
    }

    // POST /api/overtime-records
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'date'            => 'required|date',
            'start_time'      => 'required',
            'end_time'        => 'required',
            'hours'           => 'required|numeric|min:0.5',
            'overtime_reason' => 'required|string',
            'status'          => 'required',
        ]);

        $record = OvertimeRecord::create([
            'employee_id'      => $validated['employee_id'],
            'date'             => $validated['date'],
            'start_time'       => $validated['start_time'],
            'end_time'         => $validated['end_time'],
            'hours'            => $validated['hours'],
            'overtime_reason'  => $validated['overtime_reason'],
            'status'           => $validated['status'],
        ]);

        return response()->json($record, 201);
    }

    // PUT /api/overtime-records/{id}
    public function update(Request $request, $id)
    {
        $record = OvertimeRecord::find($id);

        if (!$record) {
            return response()->json([
                'message' => 'Overtime record not found.'
            ], 404);
        }

        $record->update($request->all());

        return response()->json($record, 200);
    }

    // DELETE /api/overtime-records/{id}
    public function destroy($id)
    {
        $record = OvertimeRecord::find($id);

        if (!$record) {
            return response()->json([
                'message' => 'Overtime record not found.'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'message' => 'Overtime record deleted successfully.'
        ], 200);
    }
}