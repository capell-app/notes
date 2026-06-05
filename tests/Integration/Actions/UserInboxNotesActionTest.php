<?php

declare(strict_types=1);

use Capell\Notes\Actions\AssignNoteUsersAction;
use Capell\Notes\Actions\BuildUserInboxNotesAction;
use Capell\Notes\Actions\CompleteNoteAssignmentAction;
use Capell\Notes\Actions\MarkNoteMentionsReadAction;
use Capell\Notes\Actions\MentionNoteUsersAction;
use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Models\Note;
use Capell\Tests\Fixtures\Models\User;

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

    AssignNoteUsersAction::run($assignedNote, [$user], assignedBy: null);
    MentionNoteUsersAction::run($mentionedNote, [$user], mentionedBy: null);
    AssignNoteUsersAction::run($completedAssignedNote, [$user], assignedBy: null);
    CompleteNoteAssignmentAction::run($completedAssignedNote, $user);
    AssignNoteUsersAction::run($otherPrivateNote, [$otherUser], assignedBy: null);

    $notes = BuildUserInboxNotesAction::run($user);

    expect($notes->pluck('id')->all())->toContain($assignedNote->getKey(), $mentionedNote->getKey(), $authoredPrivateNote->getKey())
        ->not->toContain($completedAssignedNote->getKey(), $otherPrivateNote->getKey());
});

it('filters inbox notes by status', function (): void {
    $user = User::factory()->create();
    $openNote = Note::factory()->create(['body' => 'Open note']);
    $resolvedNote = Note::factory()->resolved()->create(['body' => 'Resolved note']);

    AssignNoteUsersAction::run($openNote, [$user], assignedBy: null);
    MentionNoteUsersAction::run($resolvedNote, [$user], mentionedBy: null);

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

    MentionNoteUsersAction::run($displayedNote, [$user, $otherUser], mentionedBy: null);
    MentionNoteUsersAction::run($undisplayedNote, [$user], mentionedBy: null);

    $updated = MarkNoteMentionsReadAction::run($user, [$displayedNote]);

    expect($updated)->toBe(1)
        ->and($displayedNote->mentions()->whereMorphedTo('mentioned', $user)->first()->read_at)->not->toBeNull()
        ->and($displayedNote->mentions()->whereMorphedTo('mentioned', $otherUser)->first()->read_at)->toBeNull()
        ->and($undisplayedNote->mentions()->whereMorphedTo('mentioned', $user)->first()->read_at)->toBeNull();
});
