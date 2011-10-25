<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PrepareEmptyNotesScreenshotInboxAction
{
    public function handle(): void
    {
        $configuredPath = getenv('CAPELL_SCREENSHOT_APP_PATH');

        throw_unless(
            app()->environment(['local', 'testing'])
                && in_array(getenv('CAPELL_SCREENSHOT_FIXTURE'), ['1', 'true', 'record-state'], true)
                && is_string($configuredPath)
                && realpath($configuredPath) !== false
                && realpath($configuredPath) === realpath(base_path()),
            RuntimeException::class,
            'Empty Notes inbox preparation requires the explicit disposable local screenshot environment.',
        );

        DB::transaction(static function (): void {
            $notes = Note::query()->get();

            // Fail before deleting anything: an empty screenshot must never
            // require removing ordinary notes from the capture application.
            throw_if(
                $notes->contains(static fn (Note $note): bool => ! str_starts_with($note->body, '[Notes demo]')),
                RuntimeException::class,
                'Refusing to empty a screenshot inbox while non-demo notes exist.',
            );

            $notes->each(static fn (Note $note): ?bool => $note->delete());
        });
    }
}
