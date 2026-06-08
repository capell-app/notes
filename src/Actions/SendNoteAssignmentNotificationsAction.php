<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Capell\Notes\Notifications\NoteAttentionNotification;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(Note $note, array $assignees, ?Model $assignedBy = null)
 */
final class SendNoteAssignmentNotificationsAction
{
    use AsObject;

    /**
     * @param  list<Model>  $assignees
     */
    public function handle(Note $note, array $assignees, ?Model $assignedBy = null): void
    {
        foreach ($assignees as $assignee) {
            if ($this->isSameUser($assignee, $assignedBy)) {
                continue;
            }

            if (! method_exists($assignee, 'notify')) {
                continue;
            }

            $assignee->notify(new NoteAttentionNotification($note, 'assigned'));
        }
    }

    private function isSameUser(Model $user, ?Model $actor): bool
    {
        return $actor instanceof Model
            && $user->getMorphClass() === $actor->getMorphClass()
            && (string) $user->getKey() === (string) $actor->getKey();
    }
}
