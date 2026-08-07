<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Delegation;
use App\Models\Employee;
use App\Services\MqttService;

class DelegationController extends Controller
{
    public function store(Request $request)
    {
        $user   = Auth::user();
        $myRole = $user->role instanceof \App\Enums\Role
            ? $user->role->value : $user->role;

        $request->validate([
            'delegator_id' => 'required|exists:employees,id',
            'delegate_id'  => 'required|exists:employees,id|different:delegator_id',
            'start_date'   => 'required|date|after_or_equal:today',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'reason'       => 'nullable|string|max:200',
        ]);

        // Permission check:
        // Admin and HR can set delegations for anyone
        // Manager can only set delegation for themselves
        if (!in_array($myRole, ['系統管理者', '人資部'])) {
            if ($myRole === '部門主管') {
                if ((int)$request->delegator_id !== $user->id) {
                    return back()->with('error', '您只能為自己設定簽核代理。');
                }
            } else {
                return back()->with('error', '您沒有權限設定簽核代理。');
            }
        }

        // Check for overlapping active delegation for same delegator
        $overlap = Delegation::where('delegator_id', $request->delegator_id)
            ->where('is_active', true)
            ->whereDate('end_date', '>=', $request->start_date)
            ->whereDate('start_date', '<=', $request->end_date)
            ->exists();

        if ($overlap) {
            return back()->withErrors([
                'start_date' => '該主管在此期間已有有效的代理設定，請先撤銷現有代理或調整日期。'
            ])->withInput();
        }

        Delegation::create([
            'delegator_id' => $request->delegator_id,
            'delegate_id'  => $request->delegate_id,
            'start_date'   => $request->start_date,
            'end_date'     => $request->end_date,
            'reason'       => $request->reason,
            'is_active'    => true,
        ]);

        try {
            $delegator = Employee::find($request->delegator_id);
            $delegate  = Employee::find($request->delegate_id);
            $mqtt = new \App\Services\MqttService();
            $mqtt->publish('delegation/set', [
                'delegator_id'   => $delegator->id,
                'delegator_name' => $delegator->name,
                'delegator_dept' => $delegator->department,
                'delegate_id'    => $delegate->id,
                'delegate_name'  => $delegate->name,
                'delegate_no'    => $delegate->employee_no,
                'start_date'     => $request->start_date,
                'end_date'       => $request->end_date,
                'reason'         => $request->reason ?: '',
                'source'         => 'web',
                'timestamp'      => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('MQTT delegation/set failed: ' . $e->getMessage());
        }

        return redirect()->back()
            ->with('success', '簽核代理已建立。');
    }

    public function deactivate($id)
    {
        $user       = Auth::user();
        $myRole     = $user->role instanceof \App\Enums\Role
            ? $user->role->value : $user->role;
        $delegation = Delegation::findOrFail($id);

        // Permission: Admin/HR can revoke anyone's, manager can only revoke their own
        if (!in_array($myRole, ['系統管理者', '人資部'])) {
            if ($myRole === '部門主管' && $delegation->delegator_id !== $user->id) {
                return back()->with('error', '您只能撤銷自己的簽核代理。');
            }
        }

        $delegation->update(['is_active' => false]);

        try {
            $mqtt = new \App\Services\MqttService();
            $mqtt->publish('delegation/revoked', [
                'delegator_id'   => $delegation->delegator->id,
                'delegator_name' => $delegation->delegator->name,
                'delegator_dept' => $delegation->delegator->department,
                'delegate_name'  => $delegation->delegate->name,
                'timestamp'      => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('MQTT delegation/revoked failed: ' . $e->getMessage());
        }

        return redirect()->back()
            ->with('success', '簽核代理已撤銷。');
    }
}