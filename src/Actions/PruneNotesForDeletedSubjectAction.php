<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(Model $subject)
 */
final class PruneNotesForDeletedSubjectAction
{
    use AsObject;

    public function handle(Model $subject): void
    {
        Note::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->get()
            ->each(static function (Note $note): void {
                $note->delete();
            });
    }
}
