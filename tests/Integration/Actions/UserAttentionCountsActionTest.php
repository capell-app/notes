<?php

declare(strict_types=1);

use Capell\Notes\Actions\AssignNoteUsersAction;
use Capell\Notes\Actions\BuildUserAttentionCountsAction;
use Capell\Notes\Actions\CompleteNoteAssignmentAction;
use Capell\Notes\Actions\MentionNoteUsersAction;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteReminder;
use Capell\Tests\Fixtures\Models\User;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('counts assigned notes, mentions, and active reminders for the user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $assignedNote = Note::factory()->create();
    $mentionedNote = Note::factory()->create();
    $dueTodayNote = Note::factory()->create();
    $overdueNote = Note::factory()->create();
    $completedNote = Note::factory()->create();

    AssignNoteUsersAction::run($assignedNote, [$user], assignedBy: null);
    AssignNoteUsersAction::run($dueTodayNote, [$user], assignedBy: null);
    AssignNoteUsersAction::run($overdueNote, [$user], assignedBy: null);
    AssignNoteUsersAction::run($completedNote, [$user], assignedBy: null);
    AssignNoteUsersAction::run(Note::factory()->create(), [$otherUser], assignedBy: null);
    MentionNoteUsersAction::run($mentionedNote, [$user], mentionedBy: null);

    CompleteNoteAssignmentAction::run($completedNote, $user);

    NoteReminder::factory()->create([
        'note_id' => $dueTodayNote->getKey(),
        'due_at' => now()->addHour(),
        'next_due_at' => null,
    ]);

    NoteReminder::factory()->create([
        'note_id' => $overdueNote->getKey(),
        'due_at' => now()->subDay(),
        'next_due_at' => null,
    ]);

    NoteReminder::factory()->create([
        'note_id' => $completedNote->getKey(),
        'due_at' => now()->subDay(),
        'next_due_at' => null,
    ]);

    $counts = BuildUserAttentionCountsAction::run($user);

    expect($counts->assigned)->toBe(3)
        ->and($counts->mentions)->toBe(1)
        ->and($counts->dueToday)->toBe(1)
        ->and($counts->overdue)->toBe(1)
        ->and($counts->total())->toBe(6);
});
