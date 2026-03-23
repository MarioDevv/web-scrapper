<?php

declare(strict_types=1);

namespace App\Providers;

use Native\Laravel\Facades\Window;

class NativeAppServiceProvider
{
    public function boot(): void
    {
        Window::open()
            ->title('SEO Spider')
            ->width(1280)
            ->height(800)
            ->minWidth(900)
            ->minHeight(600);
    }
}
