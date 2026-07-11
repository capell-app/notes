<?php

declare(strict_types=1);

use Capell\Notes\Actions\AssignNoteUsersAction;
use Capell\Notes\Actions\BuildUserAttentionCountsAction;
use Capell\Notes\Actions\CompleteNoteAssignmentAction;
use Capell\Notes\Actions\MentionNoteUsersAction;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteReminder;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('counts assigned notes, mentions, and active reminders for the user', function (): void {
    Gate::before(static fn (): bool => true);

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $assignedNote = Note::factory()->create();
    $mentionedNote = Note::factory()->create();
    $dueTodayNote = Note::factory()->create();
    $overdueNote = Note::factory()->create();
    $completedNote = Note::factory()->create();

    AssignNoteUsersAction::run($assignedNote, [$user], assignedBy: $user);
    AssignNoteUsersAction::run($dueTodayNote, [$user], assignedBy: $user);
    AssignNoteUsersAction::run($overdueNote, [$user], assignedBy: $user);
    AssignNoteUsersAction::run($completedNote, [$user], assignedBy: $user);
    AssignNoteUsersAction::run(Note::factory()->create(), [$otherUser], assignedBy: $user);
    MentionNoteUsersAction::run($mentionedNote, [$user], mentionedBy: $user);

    CompleteNoteAssignmentAction::run($completedNote, $user);

    NoteReminder::factory()->create([
        'note_id' => $dueTodayNote->getKey(),
        'due_at' => now()->startOfDay()->addHour(),
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

it('does not count attention records after subject access is revoked', function (): void {
    $subject = User::factory()->create();
    $user = User::factory()->create();
    $userHasAccess = true;
    $assignedNote = Note::factory()->create([
        'subject_type' => $subject->getMorphClass(),
        'subject_id' => $subject->getKey(),
    ]);
    $mentionedNote = Note::factory()->create([
        'subject_type' => $subject->getMorphClass(),
        'subject_id' => $subject->getKey(),
    ]);

    Gate::before(static function (User $actor, string $ability) use (&$userHasAccess, $subject, $user): ?bool {
        if ($ability !== 'update') {
            return null;
        }

        return $actor->is($subject) || ($userHasAccess && $actor->is($user));
    });
    Gate::define('update', static function (User $actor, User $noteSubject) use (&$userHasAccess, $subject, $user): bool {
        return $actor->is($subject) || ($userHasAccess && $actor->is($user) && $noteSubject->is($subject));
    });

    AssignNoteUsersAction::run($assignedNote, [$user], assignedBy: $subject);
    MentionNoteUsersAction::run($mentionedNote, [$user], mentionedBy: $subject);
    NoteReminder::factory()->create([
        'note_id' => $assignedNote->getKey(),
        'due_at' => now()->subDay(),
        'next_due_at' => null,
    ]);

    expect(BuildUserAttentionCountsAction::run($user)->total())->toBe(3);

    $userHasAccess = false;

    $counts = BuildUserAttentionCountsAction::run($user);

    expect($counts->assigned)->toBe(0)
        ->and($counts->mentions)->toBe(0)
        ->and($counts->overdue)->toBe(0)
        ->and($counts->total())->toBe(0);
});
