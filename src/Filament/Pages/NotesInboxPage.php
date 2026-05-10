<?php

declare(strict_types=1);

namespace Capell\Notes\Filament\Pages;

use BackedEnum;
use Capell\Notes\Actions\BuildUserAttentionCountsAction;
use Capell\Notes\Data\UserAttentionCountData;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Override;

final class NotesInboxPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $slug = 'notes';

    protected static ?int $navigationSort = 80;

    protected string $view = 'capell-notes::filament.pages.notes-inbox';

    #[Override]
    public static function getNavigationLabel(): string
    {
        return (string) __('capell-notes::navigation.notes');
    }

    #[Override]
    public static function getNavigationGroup(): ?string
    {
        return (string) __('capell-admin::navigation.group_extensions');
    }

    #[Override]
    public function getTitle(): string
    {
        return (string) __('capell-notes::note.inbox_title');
    }

    public function counts(): UserAttentionCountData
    {
        $user = auth()->user();

        if (! $user instanceof Model) {
            return new UserAttentionCountData;
        }

        return BuildUserAttentionCountsAction::run($user);
    }
}
