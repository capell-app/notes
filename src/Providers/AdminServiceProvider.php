<?php

declare(strict_types=1);

namespace Capell\Notes\Providers;

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Notes\Actions\BuildUserAttentionCountsAction;
use Capell\Notes\Data\UserAttentionCountData;
use Capell\Notes\Filament\Pages\NotesInboxPage;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    /** @var array<string, UserAttentionCountData> */
    private array $attentionCounts = [];

    public function boot(): void
    {
        if (! $this->isPackageInstalled()) {
            return;
        }

        CapellAdmin::registerExtensionPage(NotesServiceProvider::$packageName, NotesInboxPage::class);
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

    private function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(NotesServiceProvider::$packageName);
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
        $cacheKey = $user->getMorphClass() . ':' . $user->getKey();

        if (! isset($this->attentionCounts[$cacheKey])) {
            $this->attentionCounts[$cacheKey] = BuildUserAttentionCountsAction::run($user);
        }

        return $this->attentionCounts[$cacheKey];
    }
}
