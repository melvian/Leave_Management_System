<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AttendanceDailyExport implements WithMultipleSheets
{
    public function __construct(
        protected $records,
        protected $onLeaveEmployees,
        protected $absentWithoutLeave,
        protected string $date
    ) {}

    public function sheets(): array
    {
        return [
            new AttendanceNormalSheet($this->records),
            new AttendanceLateSheet($this->records),
            new AttendanceEarlyLeaveSheet($this->records),
            new AttendanceOnLeaveSheet($this->onLeaveEmployees, $this->date),
            new AttendanceAbsentSheet($this->absentWithoutLeave),
        ];
    }
}