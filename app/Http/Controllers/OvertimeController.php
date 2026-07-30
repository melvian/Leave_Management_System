<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\OvertimeRecord;
use App\Models\Employee;

class OvertimeController extends Controller
{
    public function index()
    {
        $employee = Auth::user();
        $records  = $employee->overtimeRecords()
                        ->orderBy('date', 'desc')
                        ->get();
        return view('overtime.index', compact('records', 'employee'));
    }

    public function create(Request $request)
    {
        $employee = Auth::user();
        $prefill  = [ 
            'date'  => $request->get('prefill_date', ''),
            'start' => $request->get('prefill_start', ''),
            'end'   => $request->get('prefill_end', ''),
            'hours' => $request->get('prefill_hours', ''),
        ];
        return view('overtime.create', compact('employee', 'prefill'));
    }

    public function store(Request $request)
    {
        
        // Block personal overtime
        if ($request->overtime_type === 'personal') {
            return back()
                ->withErrors(['overtime_type' => '個人原因不符合加班申請資格。'])
                ->withInput();
        }
    
        $employee = Auth::user();

        $request->validate([
            'date'            => 'required|date|before_or_equal:today',
            'start_time'      => 'required',
            'end_time'        => 'required',
            'overtime_reason' => 'nullable|string|max:300',
        ]);

        // Manual time comparison using simple string math
        $startParts = explode(':', $request->start_time);
        $endParts   = explode(':', $request->end_time);
        $startMins  = (int)$startParts[0] * 60 + (int)$startParts[1];
        $endMins    = (int)$endParts[0]   * 60 + (int)$endParts[1];

        if ($endMins <= $startMins) {
            return back()->withErrors([
                'end_time' => '結束時間必須晚於開始時間。'
            ])->withInput();
        }

        $totalMins = $endMins - $startMins;
        $hours     = round($totalMins / 60, 1);

        if ($hours <= 0) {
            return back()->withErrors([
                'end_time' => '加班時數必須大於0。'
            ])->withInput();
        }

        OvertimeRecord::create([
            'employee_id'     => $employee->id,
            'date'            => $request->date,
            'start_time'      => $request->start_time,
            'end_time'        => $request->end_time,
            'hours'           => $hours,
            'overtime_reason' => $request->overtime_reason,
            'status'          => '待確認',
            'admin_note'      => null,
        ]);

        return redirect()->route('overtime.index')
            ->with('success', '加班記錄已送出，等待主管確認。');
    }

    public function show($id)
    {
        $employee = Auth::user();
        $record   = OvertimeRecord::findOrFail($id);
        return view('overtime.show', compact('record', 'employee'));
    }
}
