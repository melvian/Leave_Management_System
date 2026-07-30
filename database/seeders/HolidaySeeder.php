<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Holiday;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing public holidays to avoid duplicates
        Holiday::where('type', 'public')->delete();

        $holidays = [
            // 元旦
            ['name' => '元旦', 'start_date' => '2026-01-01', 'end_date' => '2026-01-01'],
            // 農曆春節
            ['name' => '農曆除夕', 'start_date' => '2026-02-16', 'end_date' => '2026-02-16'],
            ['name' => '春節', 'start_date' => '2026-02-17', 'end_date' => '2026-02-19'],
            ['name' => '和平紀念日', 'start_date' => '2026-02-28', 'end_date' => '2026-02-28'],
            // 兒童節 & 清明節
            ['name' => '兒童節', 'start_date' => '2026-04-03', 'end_date' => '2026-04-03'],
            ['name' => '清明節', 'start_date' => '2026-04-05', 'end_date' => '2026-04-05'],
            // 勞動節
            ['name' => '勞動節', 'start_date' => '2026-05-01', 'end_date' => '2026-05-01'],
            // 端午節
            ['name' => '端午節', 'start_date' => '2026-05-31', 'end_date' => '2026-05-31'],
            // 中秋節
            ['name' => '中秋節', 'start_date' => '2026-10-03', 'end_date' => '2026-10-03'],
            // 國慶日
            ['name' => '國慶日', 'start_date' => '2026-10-10', 'end_date' => '2026-10-10'],
        ];

        foreach ($holidays as $h) {
            Holiday::create([
                'name'       => $h['name'],
                'start_date' => $h['start_date'],
                'end_date'   => $h['end_date'],
                'type'       => 'public',
                'note'       => '國定假日',
            ]);
        }
    }
}