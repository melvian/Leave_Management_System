<?php

namespace Database\Seeders;

#use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Employee;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = [

            // ── 系統管理者 (A) ──────────────────────────
            [
                'employee_no' => 'A00001',
                'name'        => '林志遠',
                'gender'      => 'male',
                'hire_date'   => '2018-03-01',
                'department'  => '數位發展部',
                'role'        => '系統管理者',
                'password'    => Hash::make('adm123'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 0,
            ],
            [
                'employee_no' => 'A00002',
                'name'        => '陳美玲',
                'gender'      => 'female',
                'hire_date'   => '2019-07-15',
                'department'  => '數位發展部',
                'role'        => '系統管理者',
                'password'    => Hash::make('adm123'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 0,
            ],
            [
                'employee_no' => 'A00003',
                'name'        => '黃建國',
                'gender'      => 'male',
                'hire_date'   => '2020-01-10',
                'department'  => '數位發展部',
                'role'        => '系統管理者',
                'password'    => Hash::make('adm123'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 0,
            ],

            // ── 人資部 (H) ──────────────────────────────
            [
                'employee_no' => 'H00001',
                'name'        => '王淑芬',
                'gender'      => 'female',
                'hire_date'   => '2017-05-20',
                'department'  => '人資部',
                'role'        => '人資部',
                'password'    => Hash::make('hr123'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 0,
            ],
            [
                'employee_no' => 'H00002',
                'name'        => '吳雅婷',
                'gender'      => 'female',
                'hire_date'   => '2021-09-01',
                'department'  => '人資部',
                'role'        => '人資部',
                'password'    => Hash::make('hr123'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 0,
            ],
            [
                'employee_no' => 'H00003',
                'name'        => '陳俊豪',
                'gender'      => 'male',
                'hire_date'   => '2015-06-01',
                'department'  => '人資部',
                'role'        => '人資部', 
                'password'    => Hash::make('hr123'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 0,
            ],

            // ── 部門主管 (M) ────────────────────────────
            [
                'employee_no' => 'M00001',
                'name'        => '張偉翔',
                'gender'      => 'male',
                'hire_date'   => '2016-08-01',
                'department'  => '節目部',
                'role'        => '部門主管',
                'password'    => Hash::make('man1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 4,
            ],
            [
                'employee_no' => 'M00002',
                'name'        => '劉雅雯',
                'gender'      => 'female',
                'hire_date'   => '2015-03-15',
                'department'  => '新聞部',
                'role'        => '部門主管',
                'password'    => Hash::make('man1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 8,
            ],
            [
                'employee_no' => 'M00003',
                'name'        => '蔡明宏',
                'gender'      => 'male',
                'hire_date'   => '2014-11-01',
                'department'  => '數位發展部',
                'role'        => '部門主管',
                'password'    => Hash::make('man1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 2,
            ],

            // ── 員工 (E) ────────────────────────────────
            // 節目部 (3 employees)
            [
                'employee_no' => 'E00001',
                'name'        => '許雅琪',
                'gender'      => 'female',
                'hire_date'   => '2022-04-01',
                // 滿3年未滿5年 → 14天特休
                'department'  => '節目部',
                'role'        => '員工',
                'password'    => Hash::make('emp1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 0,
            ],
            [
                'employee_no' => 'E00002',
                'name'        => '鄭志豪',
                'gender'      => 'male',
                'hire_date'   => '2025-02-01',
                // 滿1年未滿2年 → 7天特休
                'department'  => '節目部',
                'role'        => '員工',
                'password'    => Hash::make('emp1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 3,
            ],
            [
                'employee_no' => 'E00003',
                'name'        => '林佳蓉',
                'gender'      => 'female',
                'hire_date'   => '2024-08-15',
                // 滿6個月未滿1年 → 3天特休
                'department'  => '節目部',
                'role'        => '員工',
                'password'    => Hash::make('emp1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 0,
            ],

            // 新聞部 (4 employees)
            [
                'employee_no' => 'E00004',
                'name'        => '楊宗翰',
                'gender'      => 'male',
                'hire_date'   => '2019-06-01',
                // 滿5年未滿10年 → 15天特休
                'department'  => '新聞部',
                'role'        => '員工',
                'password'    => Hash::make('emp1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 6,
            ],
            [
                'employee_no' => 'E00005',
                'name'        => '謝依婷',
                'gender'      => 'female',
                'hire_date'   => '2023-01-10',
                // 滿2年未滿3年 → 10天特休
                'department'  => '新聞部',
                'role'        => '員工',
                'password'    => Hash::make('emp1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 0,
            ],
            [
                'employee_no' => 'E00006',
                'name'        => '洪家豪',
                'gender'      => 'male',
                'hire_date'   => '2020-09-01',
                // 滿5年未滿10年 → 15天特休
                'department'  => '新聞部',
                'role'        => '員工',
                'password'    => Hash::make('emp1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 4,
            ],
            [
                'employee_no' => 'E00007',
                'name'        => '蘇雅文',
                'gender'      => 'female',
                'hire_date'   => '2026-03-01',
                // 未滿6個月 → 0天特休
                'department'  => '新聞部',
                'role'        => '員工',
                'password'    => Hash::make('emp1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 0,
            ],

            // 數位發展部 (3 employees)
            [
                'employee_no' => 'E00008',
                'name'        => '曾志明',
                'gender'      => 'male',
                'hire_date'   => '2013-05-01',
                // 滿10年以上 → 15+(13-10)=18天特休
                'department'  => '數位發展部',
                'role'        => '員工',
                'password'    => Hash::make('emp1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 10,
            ],
            [
                'employee_no' => 'E00009',
                'name'        => '潘怡君',
                'gender'      => 'female',
                'hire_date'   => '2022-11-01',
                // 滿2年未滿3年 → 10天特休
                'department'  => '數位發展部',
                'role'        => '員工',
                'password'    => Hash::make('emp1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 0,
            ],
            [
                'employee_no' => 'E00010',
                'name'        => '江俊賢',
                'gender'      => 'male',
                'hire_date'   => '2021-04-15',
                // 滿3年未滿5年 → 14天特休
                'department'  => '數位發展部',
                'role'        => '員工',
                'password'    => Hash::make('emp1234'),
                'is_active'   => 1,
                'compensatory_hours_remaining' => 8,
            ],
        ];

        foreach ($employees as $data) {
            Employee::create($data);
        }
    }
}
