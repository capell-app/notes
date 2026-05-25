<?php

declare(strict_types=1);

namespace Capell\Notes\Providers;

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Page;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\Notes\Filament\Extenders\Page\CreateNoteResourceHeaderActionExtender;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteAssignment;
use Capell\Notes\Models\NoteMention;
use Capell\Notes\Models\NoteReminder;
use Capell\Notes\Support\NotesManager;
use Illuminate\Database\Eloquent\Model;
use Override;
use Spatie\LaravelPackageTools\Package;

class NotesServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-notes';

    public static string $packageName = 'capell-app/notes';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasTranslations()
            ->hasViews()
            ->hasMigrations(['2026_05_10_190862_01_create_notes_tables']);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(NotesManager::class);
        $this->app->register(AdminServiceProvider::class);
        $this->app->tag([CreateNoteResourceHeaderActionExtender::class], ResourceHeaderActionExtender::TAG);
    }

    public function packageRegistered(): void
    {
        $this->app->booted(function (): void {
            if (! $this->isPackageInstalled()) {
                return;
            }

            $this->registerModels();
            $this->registerDefaultSubjects();
            $this->registerDefaultParticipants();
            $this->registerProtectedTables();
        });
    }

    #[Override]
    protected function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(static::$packageName);
    }

    private function registerModels(): self
    {
        CapellCore::registerModels([
            Note::class,
            NoteAssignment::class,
            NoteMention::class,
            NoteReminder::class,
        ]);

        return $this;
    }

    private function registerProtectedTables(): self
    {
        CapellCore::registerProtectedTable('notes');
        CapellCore::registerProtectedTable('note_assignments');
        CapellCore::registerProtectedTable('note_mentions');
        CapellCore::registerProtectedTable('note_reminders');

        return $this;
    }

    private function registerDefaultParticipants(): self
    {
        $userModel = config('auth.providers.users.model');

        if (is_string($userModel) && is_a($userModel, Model::class, true)) {
            resolve(NotesManager::class)->registerParticipant($userModel);
        }

        return $this;
    }

    private function registerDefaultSubjects(): self
    {
        resolve(NotesManager::class)->registerSubject(Page::class);

        return $this;
    }
}
