<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>員工差勤暨請假管理系統</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --navy:       #1F3864;
            --navy-mid:   #2E74B5;
            --navy-light: #D6E4F0;
            --teal:       #0E7C86;
            --teal-light: #E1F5EE;
        }
        body { background: #F4F7FB; font-family: 'Microsoft JhengHei', sans-serif; }

        /* Sidebar */
        #sidebar {
            width: 240px; min-height: 100vh;
            background: var(--navy);
            position: fixed; top: 0; left: 0; z-index: 100;
            display: flex; flex-direction: column;
        }
        #sidebar .brand {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        #sidebar .brand-title {
            font-size: 14px; font-weight: 700; color: white; line-height: 1.4;
        }
        #sidebar .brand-sub {
            font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 2px;
        }
        #sidebar .nav-section {
            font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.4);
            letter-spacing: 1px; padding: 16px 20px 6px; text-transform: uppercase;
        }
        #sidebar .nav-link {
            color: rgba(255,255,255,0.75); padding: 10px 20px;
            font-size: 14px; border-left: 3px solid transparent;
            transition: all 0.15s;
        }
        #sidebar .nav-link:hover,
        #sidebar .nav-link.active {
            color: white; background: rgba(255,255,255,0.08);
            border-left-color: #2E74B5;
        }
        #sidebar .nav-link i { width: 20px; margin-right: 8px; }
        #sidebar .sidebar-footer {
            margin-top: auto; padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        #sidebar .user-name { font-size: 13px; font-weight: 600; color: white; }
        #sidebar .user-role {
            font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 2px;
        }

        /* Main content */
        #main { margin-left: 240px; min-height: 100vh; }
        .topbar {
            background: white; border-bottom: 1px solid #e0e6ef;
            padding: 14px 28px; display: flex; align-items: center;
            justify-content: space-between; position: sticky; top: 0; z-index: 50;
        }
        .topbar .page-title { font-size: 18px; font-weight: 700; color: var(--navy); margin: 0; }
        .content-area { padding: 28px; }

        /* Cards */
        .card { border: none; box-shadow: 0 1px 4px rgba(0,0,0,0.07); border-radius: 10px; }
        .card-header-navy { background: var(--navy); color: white; border-radius: 10px 10px 0 0 !important; }

        /* Stat cards */
        .stat-card {
            background: white; border-radius: 10px;
            padding: 18px 20px; border-left: 4px solid var(--navy-mid);
            box-shadow: 0 1px 4px rgba(0,0,0,0.07);
        }
        .stat-card .stat-label { font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-value { font-size: 28px; font-weight: 700; color: var(--navy); line-height: 1.1; }
        .stat-card .stat-sub { font-size: 12px; color: #aaa; margin-top: 2px; }

        /* Tables */
        .table thead th { background: var(--navy); color: white; font-weight: 600; font-size: 13px; border: none; }
        .table tbody tr:hover { background: #f0f5ff; }
        .table td, .table th { vertical-align: middle; }

        /* Badges */
        .badge-草稿    { background: #e9ecef; color: #666; }
        .badge-簽核中  { background: #fff3cd; color: #856404; }
        .badge-已核准  { background: #d1e7dd; color: #0f5132; }
        .badge-已拒絕  { background: #f8d7da; color: #842029; }
        .badge-待確認  { background: #e9ecef; color: #666; }
        .badge-已確認  { background: #d1e7dd; color: #0f5132; }
        .badge-已駁回  { background: #f8d7da; color: #842029; }

        /* Forms */
        .form-label { font-weight: 600; font-size: 14px; color: #444; }
        .form-control:focus, .form-select:focus {
            border-color: var(--navy-mid);
            box-shadow: 0 0 0 3px rgba(46,116,181,0.15);
        }
        .btn-navy { background: var(--navy); color: white; border: none; }
        .btn-navy:hover { background: #162b4d; color: white; }
        .btn-teal { background: var(--teal); color: white; border: none; }
        .btn-teal:hover { background: #0a5f68; color: white; }

        /* Alert info box */
        .info-box {
            background: var(--navy-light); border-left: 4px solid var(--navy-mid);
            border-radius: 6px; padding: 14px 18px; font-size: 14px;
        }
    </style>
</head>
<body>

@auth

@php
    $navRole = Auth::user()->role instanceof \App\Enums\Role
        ? Auth::user()->role->value
        : Auth::user()->role;
    $currentRoute = request()->route()->getName() ?? '';

    // Check if user is an active delegate — give them manager nav access
    $isActiveDelegate = \App\Models\Delegation::where('delegate_id', Auth::user()->id)
        ->where('is_active', true)
        ->whereDate('start_date', '<=', now()->toDateString())
        ->whereDate('end_date',   '>=', now()->toDateString())
        ->exists();

    $effectiveRole = $isActiveDelegate ? '部門主管' : $navRole;
@endphp

<!-- Sidebar -->
<div id="sidebar">
    <div class="brand">
        <div class="brand-title">差勤管理系統</div>
        <div class="brand-sub">員工差勤暨請假管理</div>
    </div>

    <div class="nav-section">主選單</div>
    <a href="{{ route('dashboard') }}"
        class="nav-link {{ str_starts_with($currentRoute,'dashboard') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i> 儀表板
    </a>
    <a href="{{ route('attendance.index') }}"
        class="nav-link {{ str_starts_with($currentRoute,'attendance.') && !str_starts_with($currentRoute,'attendance.management') ? 'active' : '' }}">
        <i class="bi bi-briefcase"></i> 打卡記錄
    </a>
    <a href="{{ route('leave.index') }}"
        class="nav-link {{ str_starts_with($currentRoute,'leave.') ? 'active' : '' }}">
        <i class="bi bi-calendar-check"></i> 我的請假
    </a>
    <a href="{{ route('overtime.index') }}"
        class="nav-link {{ str_starts_with($currentRoute,'overtime.') ? 'active' : '' }}">
        <i class="bi bi-clock-history"></i> 我的加班
    </a>

    @if(in_array($effectiveRole, ['部門主管','人資部','系統管理者']))
    <div class="nav-section">審核管理</div>
    <a href="{{ route('approval.leave.index') }}"
        class="nav-link {{ str_starts_with($currentRoute,'approval.leave') ? 'active' : '' }}">
        <i class="bi bi-clipboard-check"></i> 請假審核
    </a>
    <a href="{{ route('approval.overtime.index') }}"
        class="nav-link {{ str_starts_with($currentRoute,'approval.overtime') ? 'active' : '' }}">
        <i class="bi bi-check2-square"></i> 加班確認
    </a>
    @endif

    @if(in_array($effectiveRole, ['人資部','系統管理者']) || ($effectiveRole === '部門主管' && Auth::user()->department === '人資部'))
    <div class="nav-section">組織管理</div>
    <a href="{{ route('employee.index') }}"
        class="nav-link {{ str_starts_with($currentRoute,'employee.') ? 'active' : '' }}">
        <i class="bi bi-people"></i> 員工管理
    </a>
    <a href="{{ route('departments.index') }}"
        class="nav-link {{ str_starts_with($currentRoute,'departments.') ? 'active' : '' }}">
        <i class="bi bi-diagram-2"></i> 部門管理
    </a>
    <a href="{{ route('attendance.management.index') }}"
        class="nav-link {{ str_starts_with($currentRoute,'attendance.management') ? 'active' : '' }}">
        <i class="bi bi-clipboard-data"></i> 考勤管理
    </a>
    <a href="{{ route('holidays.index') }}"
        class="nav-link {{ str_starts_with($currentRoute,'holidays.') ? 'active' : '' }}">
        <i class="bi bi-calendar-x"></i> 假日管理
    </a>
    @endif

    @if($effectiveRole === '系統管理者')
    <div class="nav-section">系統</div>
    <a href="{{ route('settings.index') }}"
        class="nav-link {{ str_starts_with($currentRoute,'settings.') ? 'active' : '' }}">
        <i class="bi bi-gear"></i> 系統設定
    </a>
    @endif

    <div class="sidebar-footer">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white"
                style="width:32px;height:32px;font-size:13px;flex-shrink:0;">
                {{ mb_substr(Auth::user()->name, 0, 1) }}
            </div>
            <div>
                <div class="user-name">{{ Auth::user()->name }}</div>
                <div class="user-role">{{ Auth::user()->employee_no }} ｜ {{ $effectiveRole }}</div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-sm w-100"
                style="background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.75);border:1px solid rgba(255,255,255,0.2);">
                <i class="bi bi-box-arrow-right"></i> 登出
            </button>
        </form>
    </div>
</div>

<!-- Main -->
<div id="main">
    <div class="topbar">
        <h1 class="page-title">@yield('page-title', '儀表板')</h1>
        <div style="font-size:13px;color:#888;">
            {{ now()->format('Y年m月d日') }}
            @if($isActiveDelegate)
                <span class="badge bg-warning text-dark ms-2">
                    <i class="bi bi-person-check me-1"></i>代理審核中
                </span>
            @endif
        </div>
    </div>

    <div class="content-area">

        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @yield('content')
    </div>
</div>

@endauth

@guest
@yield('content')
@endguest

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>