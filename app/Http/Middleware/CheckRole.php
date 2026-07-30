<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user     = Auth::user();
        $userRole = $user->role instanceof \App\Enums\Role
            ? $user->role->value
            : $user->role;

        // Special case: 部門主管 in 人資部 gets HR access
        if (in_array('人資部', $roles) && $userRole === '部門主管'
            && $user->department === '人資部') {
            return $next($request);
        }

        // Special case: active delegate gets manager-level access
        if (in_array('部門主管', $roles)) {
            $isDelegate = \App\Models\Delegation::where('delegate_id', $user->id)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date',   '>=', now()->toDateString())
                ->exists();

            if ($isDelegate) {
                return $next($request);
            }
        }

        if (!in_array($userRole, $roles)) {
            abort(403, '您沒有權限存取此頁面。');
        }

        return $next($request);
    }
}