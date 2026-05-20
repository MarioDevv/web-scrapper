<?php

use SeoSpider\Auditing\Infrastructure\Delivery\Livewire\SpiderDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', SpiderDashboard::class);