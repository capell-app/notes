<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Data\CreateNoteData;
use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Models\Note;
use Capell\Notes\Support\NotesManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

class CreateNoteAction
{
    use AsObject;

    public function handle(CreateNoteData $data): Note
    {
        $this->validate($data);

        return DB::transaction(function () use ($data): Note {
            $note = Note::query()->create([
                'subject_type' => $data->subject->getMorphClass(),
                'subject_id' => $data->subject->getKey(),
                'author_type' => $data->author->getMorphClass(),
                'author_id' => $data->author->getKey(),
                'body' => trim($data->body),
                'status' => NoteStatus::Open,
                'visibility' => $data->visibility,
                'resolved_at' => null,
            ]);

            AssignNoteUsersAction::run($note, $data->assignees, assignedBy: $data->author);
            MentionNoteUsersAction::run($note, $data->mentions, mentionedBy: $data->author);

            return $note->load([
                'assignments.assignee',
                'assignments.assignedBy',
                'author',
                'mentions.mentioned',
                'mentions.mentionedBy',
                'subject',
            ]);
        });
    }

    private function validate(CreateNoteData $data): void
    {
        if (trim($data->body) === '') {
            throw ValidationException::withMessages([
                'body' => __('capell-notes::note.validation.body_required'),
            ]);
        }

        $notes = resolve(NotesManager::class);
        $notes->ensureSubject($data->subject);
        $notes->ensureParticipant($data->author);

        foreach ($data->assignees as $assignee) {
            $notes->ensureParticipant($assignee);
        }

        foreach ($data->mentions as $mentioned) {
            $notes->ensureParticipant($mentioned);
        }
    }
}
