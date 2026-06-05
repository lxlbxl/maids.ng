<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\KnowledgeService::class);
        $this->app->singleton(\App\Services\AgentEventLogger::class);
        $this->app->singleton(\App\Services\ActionDispatcher::class);
        $this->app->singleton(\App\Services\AgentOverrideService::class);
        $this->app->singleton(\App\Services\HumanExecutionService::class);
        $this->app->singleton(\App\Services\ChannelSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
