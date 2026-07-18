<?php

declare(strict_types=1);

namespace Capell\Notes\Providers;

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\Admin\Filament\Resources\Pages\Pages\EditPage;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Page;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\Notes\Console\DemoCommand;
use Capell\Notes\Console\SendDueNoteRemindersCommand;
use Capell\Notes\Filament\Extenders\Page\CreateNoteResourceHeaderActionExtender;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteAssignment;
use Capell\Notes\Models\NoteMention;
use Capell\Notes\Models\NoteReminder;
use Capell\Notes\Policies\NotePolicy;
use Capell\Notes\Support\NotesManager;
use Capell\Notes\Support\UserAttentionCountsCache;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Override;
use Spatie\LaravelPackageTools\Package;

final class NotesServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-notes';

    public static string $packageName = 'capell-app/notes';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasCommand(DemoCommand::class)
            ->hasCommand(SendDueNoteRemindersCommand::class)
            ->hasMigrations([
                '2026_05_10_190862_01_create_notes_tables',
                '2026_07_10_000002_encrypt_note_bodies',
            ]);
    }

    public function registeringPackage(): void
    {
        parent::registeringPackage();

        Gate::policy(Note::class, NotePolicy::class);
        $this->app->singleton(NotesManager::class);
        $this->app->scoped(UserAttentionCountsCache::class);
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
            $this->registerReminderSchedule();
        });
    }

    #[Override]
    protected function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(self::$packageName);
    }

    private function registerModels(): self
    {
        $models = [
            Note::class,
            NoteAssignment::class,
            NoteMention::class,
            NoteReminder::class,
        ];

        $this->surface()->models($models);
        CapellCore::registerModels($models);

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
        resolve(NotesManager::class)->registerSubject(Page::class, [EditPage::class]);

        return $this;
    }

    private function registerReminderSchedule(): self
    {
        if (config('capell-notes.reminders.schedule_enabled', true) !== true) {
            return $this;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('capell:notes:send-due-reminders')
                ->everyFiveMinutes()
                ->withoutOverlapping()
                ->onOneServer();
        });

        return $this;
    }
}
