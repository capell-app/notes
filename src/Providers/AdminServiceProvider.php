<?php

declare(strict_types=1);

namespace Capell\Notes\Providers;

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Support\Packages\RegistersInstalledRuntime;
use Capell\Notes\Data\UserAttentionCountData;
use Capell\Notes\Filament\Pages\NotesInboxPage;
use Capell\Notes\Support\UserAttentionCountsCache;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Override;

class AdminServiceProvider extends ServiceProvider
{
    use RegistersInstalledRuntime;

    #[Override]
    public function register(): void
    {
        $this->registerInstalledRuntime(NotesServiceProvider::$packageName, 'admin');
    }

    public function boot(): void {}

    protected function bootInstalledRuntime(): void
    {
        CapellAdmin::registerExtensionPage(NotesServiceProvider::$packageName, NotesInboxPage::class);
        $this->registerUserMenuItem();
    }

    private function registerUserMenuItem(): void
    {
        CapellAdmin::registerUserMenuItem(
            key: 'capell-notes.inbox',
            label: fn (): string => (string) __('capell-notes::navigation.notes'),
            icon: Heroicon::OutlinedBell,
            url: fn (): string => NotesInboxPage::getUrl(),
            badge: fn (): int => $this->attentionBadgeCount(),
            badgeColor: fn (): string => $this->attentionBadgeColor(),
            sort: 70,
        );
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
