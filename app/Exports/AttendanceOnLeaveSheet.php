<?php

namespace App\Exports;

use App\Models\LeaveRequest;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class AttendanceOnLeaveSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(
        protected Collection $onLeaveEmployees,
        protected string $date
    ) {}

    public function title(): string { return '已請假'; }

    public function headings(): array
    {
        return ['員工編號','姓名','部門','假別','假期日期','天數/時數','事由'];
    }

    public function collection()
    {
        return $this->onLeaveEmployees->map(function ($emp) {
            $leave = LeaveRequest::where('employee_id', $emp->id)
                ->where('status', '已核准')
                ->whereDate('start_date', '<=', $this->date)
                ->whereDate('end_date', '>=', $this->date)
                ->first();

            $lt = $leave?->leave_type instanceof \App\Enums\LeaveType
                ? $leave->leave_type->value
                : $leave?->leave_type;

            return [
                $emp->employee_no,
                $emp->name,
                $emp->department,
                $lt ?? '—',
                ($leave?->start_date?->format('Y/m/d') ?? '')
                    . ' ~ '
                    . ($leave?->end_date?->format('Y/m/d') ?? ''),
                $leave?->hours
                    ? $leave->hours.'小時 ('.$leave->start_time.'–'.$leave->end_time.')'
                    : ($leave?->days ?? '').'天',
                $leave?->leave_reason ?? '—',
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