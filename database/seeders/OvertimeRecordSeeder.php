<?php

namespace Database\Seeders;

#use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\OvertimeRecord;
use App\Models\Employee;

class OvertimeRecordSeeder extends Seeder
{
    public function run(): void
    {
        $id = fn($no) => Employee::where('employee_no', $no)->value('id');

        $records = [
            // 已確認
            [
                'employee_id'     => $id('E00004'),
                'overtime_reason' => '節目後製趕工',
                'date'            => '2026-06-18',
                'start_time'      => '18:00',
                'end_time'        => '21:00',
                'hours'           => 3.0,
                'status'          => '已確認',
                'admin_note'      => null,
            ],
            [
                'employee_id'     => $id('E00006'),
                'overtime_reason' => '新聞稿緊急修訂',
                'date'            => '2026-06-20',
                'start_time'      => '19:00',
                'end_time'        => '22:30',
                'hours'           => 3.5,
                'status'          => '已確認',
                'admin_note'      => null,
            ],
            [
                'employee_id'     => $id('E00008'),
                'overtime_reason' => '系統維護作業',
                'date'            => '2026-06-22',
                'start_time'      => '20:00',
                'end_time'        => '22:00',
                'hours'           => 2.0,
                'status'          => '已確認',
                'admin_note'      => null,
            ],
            // 待確認
            [
                'employee_id'     => $id('E00001'),
                'overtime_reason' => '節目腳本撰寫',
                'date'            => '2026-07-05',
                'start_time'      => '18:30',
                'end_time'        => '21:30',
                'hours'           => 3.0,
                'status'          => '待確認',
                'admin_note'      => null,
            ],
            [
                'employee_id'     => $id('E00009'),
                'overtime_reason' => '資料庫備份作業',
                'date'            => '2026-07-06',
                'start_time'      => '18:00',
                'end_time'        => '20:00',
                'hours'           => 2.0,
                'status'          => '待確認',
                'admin_note'      => null,
            ],
            // 已駁回
            [
                'employee_id'     => $id('E00002'),
                'overtime_reason' => '個人專案研究',
                'date'            => '2026-06-28',
                'start_time'      => '18:00',
                'end_time'        => '20:00',
                'hours'           => 2.0,
                'status'          => '已駁回',
                'admin_note'      => '非公司指派工作，不予計入加班時數。',
            ],
        ];

        foreach ($records as $data) {
            OvertimeRecord::create($data);
        }
    }
}
