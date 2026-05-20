<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Native\Laravel\Facades\AutoUpdater;

class AppUpdater extends Component
{
    public string $state = 'idle'; // idle, checking, available, downloading, ready, error, up-to-date
    public string $newVersion = '';
    public string $releaseNotes = '';
    public int $downloadPercent = 0;
    public string $errorMessage = '';

    public function checkForUpdates(): void
    {
        $this->state = 'checking';
        $this->reset(['errorMessage', 'newVersion', 'releaseNotes', 'downloadPercent']);
        AutoUpdater::checkForUpdates();
    }

    #[On('updater-update-available')]
    public function onUpdateAvailable(string $version = '', string $releaseNotes = ''): void
    {
        $this->state = 'available';
        $this->newVersion = $version;
        $this->releaseNotes = $releaseNotes;
    }

    #[On('updater-update-not-available')]
    public function onUpdateNotAvailable(): void
    {
        $this->state = 'up-to-date';
    }

    public function downloadUpdate(): void
    {
        $this->state = 'downloading';
        $this->downloadPercent = 0;
        AutoUpdater::downloadUpdate();
    }

    #[On('updater-download-progress')]
    public function onDownloadProgress(int $percent = 0): void
    {
        $this->downloadPercent = $percent;
    }

    #[On('updater-update-downloaded')]
    public function onUpdateDownloaded(): void
    {
        $this->state = 'ready';
    }

    public function installUpdate(): void
    {
        AutoUpdater::quitAndInstall();
    }

    public function dismiss(): void
    {
        $this->state = 'idle';
    }

    #[On('updater-error')]
    public function onError(string $message = ''): void
    {
        $this->state = 'error';
        $this->errorMessage = $message;
    }

    public function render(): View
    {
        return view('livewire.app-updater');
    }
}
