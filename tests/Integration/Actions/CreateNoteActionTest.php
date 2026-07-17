<?php

declare(strict_types=1);

use Capell\Notes\Actions\CreateNoteAction;
use Capell\Notes\Data\CreateNoteData;
use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Enums\NoteVisibility;
use Capell\Notes\Models\Note;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('creates a note attached to a record with assignments and mentions', function (): void {
    Gate::before(static fn (): bool => true);

    $subject = User::factory()->create();
    $author = User::factory()->create();
    $assignee = User::factory()->create();
    $mentioned = User::factory()->create();

    $note = CreateNoteAction::run(new CreateNoteData(
        subject: $subject,
        author: $author,
        body: 'Update this content before campaign launch.',
        visibility: NoteVisibility::RecordEditors,
        assignees: [$assignee],
        mentions: [$mentioned],
    ));
    $note->refresh();
    $noteSubject = $note->subject;
    $noteAuthor = $note->author;
    $assignment = $note->assignments()->firstOrFail();
    $mention = $note->mentions()->firstOrFail();
    $assignmentAssignee = $assignment->assignee;
    $assignmentAuthor = $assignment->assignedBy;
    $mentionedUser = $mention->mentioned;
    $mentionAuthor = $mention->mentionedBy;

    throw_unless($noteSubject instanceof User, RuntimeException::class, 'Expected the note subject to be a user.');
    throw_unless($noteAuthor instanceof User, RuntimeException::class, 'Expected the note author to be a user.');
    throw_unless($assignmentAssignee instanceof User, RuntimeException::class, 'Expected the note assignee to be a user.');
    throw_unless($assignmentAuthor instanceof User, RuntimeException::class, 'Expected the assignment author to be a user.');
    throw_unless($mentionedUser instanceof User, RuntimeException::class, 'Expected the mentioned participant to be a user.');
    throw_unless($mentionAuthor instanceof User, RuntimeException::class, 'Expected the mention author to be a user.');

    expect($noteSubject->is($subject))->toBeTrue()
        ->and($noteAuthor->is($author))->toBeTrue()
        ->and($note->body)->toBe('Update this content before campaign launch.')
        ->and($note->status)->toBe(NoteStatus::Open)
        ->and($note->visibility)->toBe(NoteVisibility::RecordEditors)
        ->and($note->resolved_at)->toBeNull()
        ->and($note->assignments)->toHaveCount(1)
        ->and($assignmentAssignee->is($assignee))->toBeTrue()
        ->and($assignmentAuthor->is($author))->toBeTrue()
        ->and($note->mentions)->toHaveCount(1)
        ->and($mentionedUser->is($mentioned))->toBeTrue()
        ->and($mentionAuthor->is($author))->toBeTrue();
});

it('rejects blank note bodies', function (): void {
    $subject = User::factory()->create();
    $author = User::factory()->create();

    expect(fn (): mixed => CreateNoteAction::run(new CreateNoteData(
        subject: $subject,
        author: $author,
        body: '  ',
    )))->toThrow(ValidationException::class);
});

it('rejects unregistered note subjects', function (): void {
    $subject = new Note;
    $author = User::factory()->create();

    expect(CreateNoteAction::canCreateForSubject($subject))->toBeFalse()
        ->and(fn (): mixed => CreateNoteAction::run(new CreateNoteData(
            subject: $subject,
            author: $author,
            body: 'This should not attach to an unsupported subject.',
        )))->toThrow(InvalidArgumentException::class, 'not been registered as a note subject');
});

it('rejects assignees and mentions without access to the note subject', function (): void {
    $author = User::factory()->create();
    $assigneeWithoutAccess = User::factory()->create();
    $mentionedWithoutAccess = User::factory()->create();

    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? $user->is($author) : null);

    expect(fn (): mixed => CreateNoteAction::run(new CreateNoteData(
        subject: $author,
        author: $author,
        body: 'Do not notify a user outside this subject scope.',
        assignees: [$assigneeWithoutAccess],
    )))->toThrow(AuthorizationException::class);

    expect(fn (): mixed => CreateNoteAction::run(new CreateNoteData(
        subject: $author,
        author: $author,
        body: 'Do not notify a user outside this subject scope.',
        mentions: [$mentionedWithoutAccess],
    )))->toThrow(AuthorizationException::class)
        ->and(Note::query()->count())->toBe(0);
});

it('rejects note bodies longer than the maximum length', function (): void {
    $subject = User::factory()->create();
    $author = User::factory()->create();

    expect(fn (): mixed => CreateNoteAction::run(new CreateNoteData(
        subject: $subject,
        author: $author,
        body: str_repeat('a', CreateNoteAction::MAX_BODY_LENGTH + 1),
    )))->toThrow(ValidationException::class);

    expect(Note::query()->count())->toBe(0);
});

it('accepts a note body at exactly the maximum length', function (): void {
    Gate::before(static fn (): bool => true);

    $subject = User::factory()->create();
    $author = User::factory()->create();

    $note = CreateNoteAction::run(new CreateNoteData(
        subject: $subject,
        author: $author,
        body: str_repeat('a', CreateNoteAction::MAX_BODY_LENGTH),
    ));

    expect(mb_strlen((string) $note->body))->toBe(CreateNoteAction::MAX_BODY_LENGTH);
});

it('rolls back the note when assignment creation fails', function (): void {
    Gate::before(static fn (): bool => true);

    $subject = User::factory()->create();
    $author = User::factory()->create();
    $failingParticipant = new class extends User
    {
        public function getMorphClass(): string
        {
            throw new RuntimeException('Participant failed');
        }
    };

    expect(fn (): mixed => CreateNoteAction::run(new CreateNoteData(
        subject: $subject,
        author: $author,
        body: 'Assign this note.',
        assignees: [User::factory()->create(), $failingParticipant],
    )))->toThrow(RuntimeException::class, 'Participant failed');

    expect(Note::query()->count())->toBe(0);
});

it('rolls back the note when mention creation fails', function (): void {
    Gate::before(static fn (): bool => true);

    $subject = User::factory()->create();
    $author = User::factory()->create();
    $failingParticipant = new class extends User
    {
        public function getMorphClass(): string
        {
            throw new RuntimeException('Participant failed');
        }
    };

    expect(fn (): mixed => CreateNoteAction::run(new CreateNoteData(
        subject: $subject,
        author: $author,
        body: 'Mention someone on this note.',
        mentions: [User::factory()->create(), $failingParticipant],
    )))->toThrow(RuntimeException::class, 'Participant failed');

    expect(Note::query()->count())->toBe(0);
});
