<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MqttService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MqttService::class, function () {
            return new MqttService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
