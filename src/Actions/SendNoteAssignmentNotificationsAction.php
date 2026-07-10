<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Capell\Notes\Notifications\NoteAttentionNotification;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(Note $note, list<Model> $assignees, ?Model $assignedBy = null)
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

            $assignee->notify(new NoteAttentionNotification((int) $note->getKey(), 'assigned'));
        }
    }

    private function isSameUser(Model $user, ?Model $actor): bool
    {
        return $actor instanceof Model
            && $user->getMorphClass() === $actor->getMorphClass()
            && $this->stringValue($user->getKey()) === $this->stringValue($actor->getKey());
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value)
            ? (string) $value
            : '';
    }
}
