<?php

namespace Dsewth\SimpleHRBAC\Providers;

use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Services\RBACService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class SimpleHRBACServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->offerPublishing();
        }

        $this->setUserModel();
    }

    public function setUserModel(): void
    {
        if (class_exists(config('simple-hrbac.user_model', '\App\Models\User'))) {
            DataHelper::useUserModel(config('simple-hrbac.user_model', '\App\Models\User'));
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/simple-hrbac.php',
            'simple-hrbac'
        );

        $this->app->singleton('rbac.service', function ($app) {
            return new RBACService($app['config']['simple-hrbac'] ?? []);
        });

        $this->app->alias('rbac.service', 'RBAC');
    }

    public function offerPublishing()
    {
        $this->publishes([
            __DIR__.'/../../config/simple-hrbac.php' => config_path('simple-hrbac.php'),
        ], 'config');
        $this->publishesMigrations([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'migrations');
    }

    protected function getMigrationFilename(string $migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');

        $filesystem = $this->app->make(Filesystem::class);

        return Collection::make($this->app->databasePath().'/migrations/')
            ->flatMap(function ($path) use ($filesystem, $migrationFileName) {
                return $filesystem->glob($path.'*_'.$migrationFileName);
            })
            ->push($this->app->databasePath()."/migrations/{$timestamp}_{$migrationFileName}")
            ->first();
    }
}
