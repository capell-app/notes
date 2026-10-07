<?php

declare(strict_types=1);

namespace Capell\Notes\Providers;

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\RegistersInstalledRuntime;
use Capell\Notes\Data\UserAttentionCountData;
use Capell\Notes\Filament\Pages\NotesInboxPage;
use Capell\Notes\Support\UserAttentionCountsCache;
use Filament\PanelRegistry;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Override;

class AdminServiceProvider extends ServiceProvider
{
    use RegistersInstalledRuntime;

    private bool $installedRuntimeBooted = false;

    private bool $userMenuItemRegistered = false;

    #[Override]
    public function register(): void
    {
        $this->registerInstalledRuntime(NotesServiceProvider::$packageName, 'admin');

        $this->app->beforeResolving(PanelRegistry::class, $this->registerUserMenuItem(...));
    }

    public function boot(): void {}

    protected function bootInstalledRuntime(): void
    {
        if ($this->installedRuntimeBooted || ! $this->isPackageInstalled()) {
            return;
        }

        CapellAdmin::registerExtensionPage(NotesServiceProvider::$packageName, NotesInboxPage::class);
        $this->registerUserMenuItem();
        $this->installedRuntimeBooted = true;
    }

    protected function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(NotesServiceProvider::$packageName);
    }

    private function registerUserMenuItem(): void
    {
        if ($this->userMenuItemRegistered || ! $this->isPackageInstalled()) {
            return;
        }

        CapellAdmin::registerUserMenuItem(
            key: 'capell-notes.inbox',
            label: fn (): string => (string) __('capell-notes::navigation.notes'),
            icon: Heroicon::OutlinedBell,
            url: fn (): string => NotesInboxPage::getUrl(),
            badge: fn (): int => $this->attentionBadgeCount(),
            badgeColor: fn (): string => $this->attentionBadgeColor(),
            sort: 70,
        );
        $this->userMenuItemRegistered = true;
    }

    private function attentionBadgeCount(): int
    {
        $user = auth()->user();

        if (! $user instanceof Model) {
            return 0;
        }

        return $this->attentionCounts($user)->total();
    }

    private function attentionBadgeColor(): string
    {
        $user = auth()->user();

        if (! $user instanceof Model) {
            return 'primary';
        }

        return $this->attentionCounts($user)->overdue > 0 ? 'danger' : 'primary';
    }

    private function attentionCounts(Model $user): UserAttentionCountData
    {
        return resolve(UserAttentionCountsCache::class)->forUser($user);
    }
}
