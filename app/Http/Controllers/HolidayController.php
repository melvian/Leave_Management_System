<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Holiday;

class HolidayController extends Controller
{
    public function index()
    {
        $holidays = Holiday::orderBy('start_date', 'desc')->get();
        return view('holiday.index', compact('holidays'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'type'       => 'required|in:public,typhoon,other',
            'note'       => 'nullable|string|max:200',
        ]);

        Holiday::create($request->only(['name','start_date','end_date','type','note']));

        return redirect()->route('holidays.index')
            ->with('success', "假日「{$request->name}」已新增。");
    }

    public function destroy($id)
    {
        $holiday = Holiday::findOrFail($id);
        $name    = $holiday->name;
        $holiday->delete();

        return redirect()->route('holidays.index')
            ->with('success', "假日「{$name}」已刪除。");
    }
}
