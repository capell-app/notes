<?php

declare(strict_types=1);

namespace Capell\Notes\Providers;

use Capell\Core\Support\Migration\MigrationRootRegistry;
use Illuminate\Support\ServiceProvider;
use Override;

final class MigrationMetadataServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {

        $this->callAfterResolving(MigrationRootRegistry::class, static function (MigrationRootRegistry $registry): void {
            $registry->register('capell-app/notes', __DIR__ . '/../../database/migrations', [
                '2026_05_10_190862_01_create_notes_tables' => ['notes', 'note_assignments', 'note_mentions', 'note_reminders'],
                '2026_11_20_000001_create_note_revisions_table' => ['note_revisions'],
            ]);
        });
    }
}
