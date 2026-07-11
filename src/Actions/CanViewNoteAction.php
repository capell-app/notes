<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Enums\NoteVisibility;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteAssignment;
use Capell\Notes\Models\NoteMention;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static bool run(Note $note, Model $user)
 */
final class CanViewNoteAction
{
    use AsObject;

    public function handle(Note $note, Model $user): bool
    {
        $subject = $note->subject;

        if (! $subject instanceof Model || ! Gate::forUser($user)->allows('update', $subject)) {
            return false;
        }

        if ($this->isAuthor($note, $user)) {
            return true;
        }

        if ($this->isParticipant($note, $user)) {
            return true;
        }

        return $note->visibility !== NoteVisibility::Private;
    }

    private static function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value)
            ? (string) $value
            : '';
    }

    private function isParticipant(Note $note, Model $user): bool
    {
        if ($this->isAssigned($note, $user)) {
            return true;
        }

        return $this->isMentioned($note, $user);
    }

    private function isAuthor(Note $note, Model $user): bool
    {
        return $note->author_type === $user->getMorphClass()
            && self::stringValue($note->author_id) === self::stringValue($user->getKey());
    }

    private function isAssigned(Note $note, Model $user): bool
    {
        $userKey = self::stringValue($user->getKey());

        return $note->assignments->contains(
            static fn (mixed $assignment): bool => $assignment instanceof NoteAssignment
                && $assignment->assignee_type === $user->getMorphClass()
                && self::stringValue($assignment->assignee_id) === $userKey
                && $assignment->completed_at === null,
        );
    }

    private function isMentioned(Note $note, Model $user): bool
    {
        $userKey = self::stringValue($user->getKey());

        return $note->mentions->contains(
            static fn (mixed $mention): bool => $mention instanceof NoteMention
                && $mention->mentioned_type === $user->getMorphClass()
                && self::stringValue($mention->mentioned_id) === $userKey,
        );
    }
}
