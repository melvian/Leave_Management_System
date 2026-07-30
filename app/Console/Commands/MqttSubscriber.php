<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttSubscriber extends Command
{
    protected $signature = 'mqtt:subscribe';
    protected $description = 'Subscribe to MQTT topics and handle incoming events';
    public function handle():void
    {
        $this->info('Connecting to MQTT broker...');

        $client = new MqttClient('localhost', 1883, 'laravel-subscriber-' . uniqid());
        $settings = (new ConnectionSettings)->setConnectTimeout(5)->setKeepAliveInterval(30);
        $client->connect($settings);

        $this->info('Connected. Listening for events...');

        // Subscribe to all attendance events
        $client->subscribe('attendance/#', function (string $topic, string $payload) {
            $data = json_decode($payload, true);
            $time = now()->format('H:i:s');

            if ($topic === 'attendance/clock-in') {
                $this->line("[{$time}] 🟢 上班打卡：{$data['employee_name']}（{$data['employee_no']}）{$data['department']}");
                if ($data['late_minutes'] > 0) {
                    $this->warn("       → 遲到 {$data['late_minutes']} 分鐘");
                } else {
                    $this->info("       → 準時");
                }
            }

            if ($topic === 'attendance/clock-out') {
                $this->line("[{$time}] 🔵 下班打卡：{$data['employee_name']} 工時 {$data['worked_hours']} 小時");
                if ($data['early_leave_minutes'] > 0) {
                    $this->warn("       → 早退 {$data['early_leave_minutes']} 分鐘");
                }
            }
        }, 1);

        // Subscribe to leave events
        $client->subscribe('leave/#', function (string $topic, string $payload) {
            $data = json_decode($payload, true);
            $time = now()->format('H:i:s');

            if ($topic === 'leave/submitted') {
                $this->line("[{$time}] 📋 新請假申請：{$data['employee_name']} 申請 {$data['leave_type']} {$data['start_date']} ~ {$data['end_date']}");
            }

            if ($topic === 'leave/approved') {
                $this->info("[{$time}] ✅ 請假核准：{$data['employee_name']} 的 {$data['leave_type']} 已由 {$data['approved_by']} 核准");
            }

            if ($topic === 'leave/rejected') {
                $this->error("[{$time}] ❌ 請假拒絕：{$data['employee_name']} 的申請已拒絕 — {$data['admin_note']}");
            }
        }, 1);

        // Subscribe to overtime events
        $client->subscribe('overtime/#', function (string $topic, string $payload) {
            $data = json_decode($payload, true);
            $time = now()->format('H:i:s');

            if ($topic === 'overtime/confirmed') {
                $this->info("[{$time}] ⏰ 加班確認：{$data['employee_name']} {$data['hours']} 小時補休已加入餘額");
            }
        }, 1);

        $this->info('Subscribed to: attendance/#, leave/#, overtime/#');
        $this->info('Press Ctrl+C to stop.');

        // Loop forever, processing incoming messages
        $client->loop(true);
    }
}
