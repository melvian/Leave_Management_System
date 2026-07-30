<?php

namespace Database\Seeders;

#use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LeaveRequest;
use App\Models\Employee;

class LeaveRequestSeeder extends Seeder
{
    public function run(): void
    {
        // Helper to get employee id by employee_no
        $id = fn($no) => Employee::where('employee_no', $no)->value('id');

        $requests = [
            // 已核准 — 短假 (≤3天, manager approved)
            [
                'employee_id'      => $id('E00001'),
                'leave_type'       => '特休假',
                'leave_reason'     => '個人事務',
                'start_date'       => '2026-06-02',
                'end_date'         => '2026-06-03',
                'days'             => 2,
                'hours'            => null,
                'status'           => '已核准',
                'current_approver' => null,
                'admin_note'       => null,
            ],
            // 已核准 — 長假 (>3天, went through HR)
            [
                'employee_id'      => $id('E00004'),
                'leave_type'       => '特休假',
                'leave_reason'     => '出國旅遊',
                'start_date'       => '2026-05-19',
                'end_date'         => '2026-05-23',
                'days'             => 5,
                'hours'            => null,
                'status'           => '已核准',
                'current_approver' => null,
                'admin_note'       => '已確認假期餘額充足，核准。',
            ],
            // 已核准 — 病假
            [
                'employee_id'      => $id('E00005'),
                'leave_type'       => '病假',
                'leave_reason'     => '感冒就醫',
                'start_date'       => '2026-06-10',
                'end_date'         => '2026-06-11',
                'days'             => 2,
                'hours'            => null,
                'status'           => '已核准',
                'current_approver' => null,
                'admin_note'       => null,
            ],
            // 已核准 — 生理假
            [
                'employee_id'      => $id('E00002'),
                'leave_type'       => '生理假',
                'leave_reason'     => '生理假',
                'start_date'       => '2026-06-15',
                'end_date'         => '2026-06-15',
                'days'             => 1,
                'hours'            => null,
                'status'           => '已核准',
                'current_approver' => null,
                'admin_note'       => null,
            ],
            // 已核准 — 小時制請假
            [
                'employee_id'      => $id('E00006'),
                'leave_type'       => '事假',
                'leave_reason'     => '銀行辦事',
                'start_date'       => '2026-06-20',
                'end_date'         => '2026-06-20',
                'days'             => 0.5,
                'hours'            => 4,
                'status'           => '已核准',
                'current_approver' => null,
                'admin_note'       => null,
            ],
            // 簽核中 — 等待主管 (≤3天)
            [
                'employee_id'      => $id('E00009'),
                'leave_type'       => '特休假',
                'leave_reason'     => '家庭聚餐',
                'start_date'       => '2026-07-10',
                'end_date'         => '2026-07-11',
                'days'             => 2,
                'hours'            => null,
                'status'           => '簽核中',
                'current_approver' => 'manager',
                'admin_note'       => null,
            ],
            // 簽核中 — 等待人資 (>3天, manager already approved)
            [
                'employee_id'      => $id('E00008'),
                'leave_type'       => '特休假',
                'leave_reason'     => '長假休息',
                'start_date'       => '2026-07-14',
                'end_date'         => '2026-07-18',
                'days'             => 5,
                'hours'            => null,
                'status'           => '簽核中',
                'current_approver' => 'hr',
                'admin_note'       => null,
            ],
            // 已拒絕
            [
                'employee_id'      => $id('E00003'),
                'leave_type'       => '特休假',
                'leave_reason'     => '私人旅遊',
                'start_date'       => '2026-06-25',
                'end_date'         => '2026-06-27',
                'days'             => 3,
                'hours'            => null,
                'status'           => '已拒絕',
                'current_approver' => null,
                'admin_note'       => '該時段人力不足，請改期申請。',
            ],
            // 草稿
            [
                'employee_id'      => $id('E00010'),
                'leave_type'       => '事假',
                'leave_reason'     => '個人事務處理',
                'start_date'       => '2026-07-20',
                'end_date'         => '2026-07-20',
                'days'             => 1,
                'hours'            => null,
                'status'           => '草稿',
                'current_approver' => null,
                'admin_note'       => null,
            ],
            // 公假
            [
                'employee_id'      => $id('E00007'),
                'leave_type'       => '公假',
                'leave_reason'     => '參加政府舉辦教育訓練',
                'start_date'       => '2026-06-05',
                'end_date'         => '2026-06-06',
                'days'             => 2,
                'hours'            => null,
                'status'           => '已核准',
                'current_approver' => null,
                'admin_note'       => null,
            ],
        ];

        foreach ($requests as $data) {
            LeaveRequest::create($data);
        }
    }
}
