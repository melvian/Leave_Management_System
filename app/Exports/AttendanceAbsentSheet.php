<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class AttendanceAbsentSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(protected Collection $absentEmployees) {}

    public function title(): string { return '未出勤（無假）'; }

    public function headings(): array
    {
        return ['員工編號','姓名','部門','角色','備註'];
    }

    public function collection()
    {
        return $this->absentEmployees->map(function ($emp) {
            $role = $emp->role instanceof \App\Enums\Role
                ? $emp->role->value : $emp->role;
            return [
                $emp->employee_no,
                $emp->name,
                $emp->department,
                $role,
                '未打卡且無核准請假',
            ];
        });
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
                  'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F3864']]],
        ];
    }
}