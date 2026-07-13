<?php

declare(strict_types=1);

namespace Capell\Notes\Policies;

use Capell\Notes\Actions\CanViewNoteAction;
use Capell\Notes\Models\Note;
use Illuminate\Database\Eloquent\Model;

final class NotePolicy
{
    public function view(Model $user, Note $note): bool
    {
        return CanViewNoteAction::run($note, $user);
    }

    public function resolve(Model $user, Note $note): bool
    {
        return $this->canManageLifecycle($user, $note);
    }

    public function reopen(Model $user, Note $note): bool
    {
        return $this->canManageLifecycle($user, $note);
    }

    public function completeAssignment(Model $user, Note $note): bool
    {
        if (! CanViewNoteAction::run($note, $user)) {
            return false;
        }

        return $note->assignments()
            ->where('assignee_type', $user->getMorphClass())
            ->where('assignee_id', $user->getKey())
            ->whereNull('completed_at')
            ->exists();
    }

    private function canManageLifecycle(Model $user, Note $note): bool
    {
        if (! CanViewNoteAction::run($note, $user)) {
            return false;
        }

        $userKey = $user->getKey();

        return ($note->author_type === $user->getMorphClass()
                && (is_int($userKey) || is_string($userKey))
                && $note->author_id === $userKey)
            || $note->assignments()
                ->where('assignee_type', $user->getMorphClass())
                ->where('assignee_id', $user->getKey())
                ->whereNull('completed_at')
                ->exists();
    }
}
