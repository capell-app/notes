<?php

declare(strict_types=1);

use Capell\Notes\Actions\AssignNoteUsersAction;
use Capell\Notes\Actions\BuildUserInboxNotesAction;
use Capell\Notes\Actions\CompleteNoteAssignmentAction;
use Capell\Notes\Actions\MarkNoteMentionsReadAction;
use Capell\Notes\Actions\MentionNoteUsersAction;
use Capell\Notes\Actions\ResolveNoteParticipantsAction;
use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Models\Note;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('lists relevant inbox notes without leaking private notes for other participants', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $assignedNote = Note::factory()->create(['body' => 'Assigned to current user']);
    $mentionedNote = Note::factory()->create(['body' => 'Mentioned current user']);
    $authoredPrivateNote = Note::factory()->private()->create([
        'author_type' => $user->getMorphClass(),
        'author_id' => $user->getKey(),
        'body' => 'Authored private note',
    ]);
    $completedAssignedNote = Note::factory()->create(['body' => 'Completed assignment']);
    $otherPrivateNote = Note::factory()->private()->create(['body' => 'Other private note']);

    AssignNoteUsersAction::run($assignedNote, [$user], assignedBy: $user);
    MentionNoteUsersAction::run($mentionedNote, [$user], mentionedBy: $user);
    AssignNoteUsersAction::run($completedAssignedNote, [$user], assignedBy: $user);
    CompleteNoteAssignmentAction::run($completedAssignedNote, $user);
    AssignNoteUsersAction::run($otherPrivateNote, [$otherUser], assignedBy: $user);

    $notes = BuildUserInboxNotesAction::run($user);

    expect($notes->pluck('id')->all())->toContain($assignedNote->getKey(), $mentionedNote->getKey(), $authoredPrivateNote->getKey())
        ->not->toContain($completedAssignedNote->getKey(), $otherPrivateNote->getKey());
});

it('filters inbox notes by status', function (): void {
    $user = User::factory()->create();
    $openNote = Note::factory()->create(['body' => 'Open note']);
    $resolvedNote = Note::factory()->resolved()->create(['body' => 'Resolved note']);

    AssignNoteUsersAction::run($openNote, [$user], assignedBy: $user);
    MentionNoteUsersAction::run($resolvedNote, [$user], mentionedBy: $user);

    $openNotes = BuildUserInboxNotesAction::run($user, NoteStatus::Open);
    $resolvedNotes = BuildUserInboxNotesAction::run($user, NoteStatus::Resolved);

    expect($openNotes->pluck('id')->all())->toBe([$openNote->getKey()])
        ->and($resolvedNotes->pluck('id')->all())->toBe([$resolvedNote->getKey()]);
});

it('marks only displayed note mentions read for the current user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $displayedNote = Note::factory()->create();
    $undisplayedNote = Note::factory()->create();

    MentionNoteUsersAction::run($displayedNote, [$user, $otherUser], mentionedBy: $user);
    MentionNoteUsersAction::run($undisplayedNote, [$user], mentionedBy: $user);

    $updated = MarkNoteMentionsReadAction::run($user, [$displayedNote]);

    expect($updated)->toBe(1)
        ->and($displayedNote->mentions()->whereMorphedTo('mentioned', $user)->first()->read_at)->not->toBeNull()
        ->and($displayedNote->mentions()->whereMorphedTo('mentioned', $otherUser)->first()->read_at)->toBeNull()
        ->and($undisplayedNote->mentions()->whereMorphedTo('mentioned', $user)->first()->read_at)->toBeNull();
});

it('does not expose cross-tenant participants or notes through tampered IDs', function (): void {
    $subject = User::factory()->create();
    $authorizedParticipant = $subject;
    $crossTenantParticipant = User::factory()->create();
    $note = Note::factory()->create([
        'subject_type' => $subject->getMorphClass(),
        'subject_id' => $subject->getKey(),
        'author_type' => $subject->getMorphClass(),
        'author_id' => $subject->getKey(),
    ]);

    Gate::define('update', static fn (User $user, User $noteSubject): bool => $user->is($noteSubject));

    $participants = ResolveNoteParticipantsAction::run($subject, [
        $authorizedParticipant->getKey(),
        $crossTenantParticipant->getKey(),
    ]);

    expect(collect($participants)->map(static fn (User $participant): int|string => $participant->getKey())->all())->toBe([$authorizedParticipant->getKey()])
        ->and(fn (): mixed => AssignNoteUsersAction::run($note, [$crossTenantParticipant], assignedBy: $subject))->toThrow(AuthorizationException::class)
        ->and(BuildUserInboxNotesAction::run($crossTenantParticipant))->toBeEmpty();
});
