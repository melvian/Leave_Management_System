<?php

namespace App\Exports;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceNormalSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(
        protected $records
    ) {}

    public function title(): string
    {
        return '正常出勤';
    }

    public function headings(): array
    {
        return ['員工編號','姓名','部門','上班打卡','下班打卡','實際工時','遲到(分鐘)','早退(分鐘)','狀態'];
    }

    public function collection()
    {
        return $this->records->where('status', 'normal')->map(function ($r) {
            return [
                $r->employee->employee_no,
                $r->employee->name,
                $r->employee->department,
                $r->clock_in  ? Carbon::parse($r->clock_in)->format('H:i')  : '—',
                $r->clock_out ? Carbon::parse($r->clock_out)->format('H:i') : '—',
                $r->worked_hours ?? '—',
                $r->late_minutes ?? 0,
                $r->early_leave_minutes ?? 0,
                $r->statusLabel(),
            ];
        });
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => [
                'fillType' => 'solid',
                'startColor' => ['rgb' => '1F3864'],
            ], 'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true]],
        ];
    }
}