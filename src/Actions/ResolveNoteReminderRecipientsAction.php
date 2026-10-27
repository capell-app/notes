<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static list<Model> run(Note $note) */
final class ResolveNoteReminderRecipientsAction
{
    use AsFake;
    use AsObject;

    /** @return list<Model> */
    public function handle(Note $note): array
    {
        $note->load(['assignments.assignee', 'mentions', 'subject']);
        $recipients = [];
        foreach ($note->assignments as $assignment) {
            $assignee = $assignment->assignee;
            if ($assignment->completed_at === null && $assignee instanceof Model
                && method_exists($assignee, 'notify') && CanViewNoteAction::run($note, $assignee)) {
                $recipients[] = $assignee;
            }
        }

        return $recipients;
    }
}
