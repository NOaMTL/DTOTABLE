<?php

namespace App\Providers;

use App\QueryBuilders\TableauQueryBuilder;
use App\Repositories\ExportLogRepository;
use App\Repositories\TableauRepository;
use App\Services\DataImportService;
use App\Services\ExportLogService;
use App\Services\PdfExportService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Enregistrement des singletons
        $this->app->singleton(TableauQueryBuilder::class);
        $this->app->singleton(TableauRepository::class);
        $this->app->singleton(ExportLogRepository::class);
        $this->app->singleton(PdfExportService::class);
        $this->app->singleton(DataImportService::class);
        $this->app->singleton(ExportLogService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
