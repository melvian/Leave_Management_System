<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttService
{
    protected MqttClient $client;

    public function __construct()
    {
        $this->client = new MqttClient('localhost', 1883, 'laravel-' . uniqid());
        $settings = (new ConnectionSettings)->setConnectTimeout(3);
        $this->client->connect($settings);
    }

    public function publish(string $topic, array $payload, int $qos = 1): void
    {
        $this->client->publish($topic, json_encode($payload), $qos);
        $this->client->disconnect();
    }
}