<?php

declare(strict_types=1);

namespace App\Providers;

use App\Livewire\AuditPageTable;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Livewire's auto-discovery scans app/Livewire by class
        // namespace, but registering explicitly keeps the alias stable
        // regardless of where the parent dashboard is namespaced.
        Livewire::component('audit-page-table', AuditPageTable::class);
    }
}
