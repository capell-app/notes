<?php

declare(strict_types=1);

use Capell\Notes\Actions\AssignNoteUsersAction;
use Capell\Notes\Actions\CompleteNoteAssignmentAction;
use Capell\Notes\Actions\MentionNoteUsersAction;
use Capell\Notes\Actions\ReopenNoteAction;
use Capell\Notes\Actions\ResolveNoteAction;
use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Models\Note;
use Capell\Notes\Notifications\NoteAttentionNotification;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('assigning the same user twice does not duplicate assignment', function (): void {
    Gate::before(static fn (): bool => true);

    $note = Note::factory()->create();
    $assignee = User::factory()->create();
    $assignedBy = User::factory()->create();

    AssignNoteUsersAction::run($note, [$assignee], assignedBy: $assignedBy);
    AssignNoteUsersAction::run($note, [$assignee], assignedBy: $assignedBy);

    expect($note->assignments()->whereMorphedTo('assignee', $assignee)->count())->toBe(1)
        ->and($note->assignments()->first()->assignedBy->is($assignedBy))->toBeTrue();
});

it('mentioning the same user twice does not duplicate mention', function (): void {
    Gate::before(static fn (): bool => true);

    $note = Note::factory()->create();
    $mentioned = User::factory()->create();
    $mentionedBy = User::factory()->create();

    MentionNoteUsersAction::run($note, [$mentioned], mentionedBy: $mentionedBy);
    MentionNoteUsersAction::run($note, [$mentioned], mentionedBy: $mentionedBy);

    expect($note->mentions()->whereMorphedTo('mentioned', $mentioned)->count())->toBe(1)
        ->and($note->mentions()->first()->mentionedBy->is($mentionedBy))->toBeTrue();
});

it('rolls back standalone assignment batches when a later assignee fails', function (): void {
    Gate::before(static fn (): bool => true);

    $note = Note::factory()->create();
    $assignee = User::factory()->create();
    $failingParticipant = new class extends User
    {
        public function getMorphClass(): string
        {
            throw new RuntimeException('Participant failed');
        }
    };

    expect(fn (): mixed => AssignNoteUsersAction::run($note, [$assignee, $failingParticipant], assignedBy: $assignee))
        ->toThrow(RuntimeException::class, 'Participant failed');

    expect($note->assignments()->count())->toBe(0);
});

it('rolls back standalone mention batches when a later mention fails', function (): void {
    Gate::before(static fn (): bool => true);

    $note = Note::factory()->create();
    $mentioned = User::factory()->create();
    $failingParticipant = new class extends User
    {
        public function getMorphClass(): string
        {
            throw new RuntimeException('Participant failed');
        }
    };

    expect(fn (): mixed => MentionNoteUsersAction::run($note, [$mentioned, $failingParticipant], mentionedBy: $mentioned))
        ->toThrow(RuntimeException::class, 'Participant failed');

    expect($note->mentions()->count())->toBe(0);
});

it('completes only the current assignee assignment', function (): void {
    Gate::before(static fn (): bool => true);

    $note = Note::factory()->create();
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    AssignNoteUsersAction::run($note, [$firstUser, $secondUser], assignedBy: $firstUser);

    CompleteNoteAssignmentAction::run($note, $firstUser);

    expect($note->assignments()->whereMorphedTo('assignee', $firstUser)->first()->completed_at)->not->toBeNull()
        ->and($note->assignments()->whereMorphedTo('assignee', $secondUser)->first()->completed_at)->toBeNull();
});

it('reactivates a completed assignment when the user is assigned again', function (): void {
    Gate::before(static fn (): bool => true);

    $note = Note::factory()->create();
    $assignee = User::factory()->create();

    AssignNoteUsersAction::run($note, [$assignee], assignedBy: $assignee);
    CompleteNoteAssignmentAction::run($note, $assignee);

    expect($note->assignments()->whereMorphedTo('assignee', $assignee)->first()->completed_at)->not->toBeNull();

    AssignNoteUsersAction::run($note, [$assignee], assignedBy: $assignee);

    expect($note->assignments()->whereMorphedTo('assignee', $assignee)->first()->completed_at)->toBeNull();
});

it('reactivates a read mention when the user is mentioned again', function (): void {
    Gate::before(static fn (): bool => true);

    $note = Note::factory()->create();
    $mentioned = User::factory()->create();

    MentionNoteUsersAction::run($note, [$mentioned], mentionedBy: $mentioned);

    $note->mentions()->whereMorphedTo('mentioned', $mentioned)->update(['read_at' => now()]);

    expect($note->mentions()->whereMorphedTo('mentioned', $mentioned)->first()->read_at)->not->toBeNull();

    MentionNoteUsersAction::run($note, [$mentioned], mentionedBy: $mentioned);

    expect($note->mentions()->whereMorphedTo('mentioned', $mentioned)->first()->read_at)->toBeNull();
});

it('resolving and reopening note updates status and timestamps correctly', function (): void {
    Gate::before(static fn (): bool => true);

    $note = Note::factory()->create();

    ResolveNoteAction::run($note, $note->author);

    expect($note->refresh()->status)->toBe(NoteStatus::Resolved)
        ->and($note->resolved_at)->not->toBeNull();

    ReopenNoteAction::run($note, $note->author);

    expect($note->refresh()->status)->toBe(NoteStatus::Open)
        ->and($note->resolved_at)->toBeNull();
});

it('does not allow a mentioned participant to resolve a note', function (): void {
    $author = User::factory()->create();
    $mentionedParticipant = User::factory()->create();
    $note = Note::factory()->create([
        'subject_type' => $author->getMorphClass(),
        'subject_id' => $author->getKey(),
        'author_type' => $author->getMorphClass(),
        'author_id' => $author->getKey(),
    ]);

    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update'
        ? $user->is($author) || $user->is($mentionedParticipant)
        : null);

    MentionNoteUsersAction::run($note, [$mentionedParticipant], mentionedBy: $author);

    expect(fn (): mixed => ResolveNoteAction::run($note, $mentionedParticipant))->toThrow(AuthorizationException::class)
        ->and($note->refresh()->status)->toBe(NoteStatus::Open);
});

it('keeps note content and subject identifiers out of notification payloads', function (): void {
    $note = Note::factory()->create(['body' => 'Confidential launch plan.']);
    $recipient = User::factory()->create();

    $payload = (new NoteAttentionNotification($note, 'assigned'))->toArray($recipient);

    expect($payload)->toBe([
        'type' => 'assigned',
        'note_id' => $note->getKey(),
    ]);
});
