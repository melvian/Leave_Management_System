<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Employee;

class AuthController extends Controller
{
    // Show login form
    public function showLogin()
    {
        return view('auth.login');
    }

    // Handle login
    public function login(Request $request)
    {
        $request->validate([
            'employee_no' => 'required',
            'password'    => 'required',
        ]);

        // Find employee regardless of active status
        $emp = Employee::where('employee_no', $request->employee_no)->first();

        // Employee not found
        if (!$emp) {
            return back()->withErrors([
                'employee_no' => '員工編號或密碼錯誤，請重新輸入。',
            ])->withInput();
        }

        // Account inactive
        if (!$emp->is_active) {
            return back()->withErrors([
                'employee_no' => '此帳號已停用，請聯絡人資部。',
            ])->withInput();
        }

        // Password incorrect
        if (!Hash::check($request->password, $emp->password)) {
            return back()->withErrors([
                'employee_no' => '員工編號或密碼錯誤，請重新輸入。',
            ])->withInput();
        }

        Auth::login($emp);

        return redirect()->route('dashboard');
    }

    // Handle logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
