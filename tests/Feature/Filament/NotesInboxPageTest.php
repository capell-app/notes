<?php

declare(strict_types=1);

use Capell\Notes\Actions\AssignNoteUsersAction;
use Capell\Notes\Actions\BuildUserAttentionCountsAction;
use Capell\Notes\Actions\MentionNoteUsersAction;
use Capell\Notes\Filament\Pages\NotesInboxPage;
use Capell\Notes\Models\Note;
use Capell\Tests\Fixtures\Models\User;
use Livewire\Livewire;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('renders inbox notes and marks displayed mentions read after preserving initial counts', function (): void {
    $user = User::factory()->create(['name' => 'Editor One']);
    $assignedNote = Note::factory()->create(['body' => 'Follow up with legal before publish.']);
    $mentionedNote = Note::factory()->create(['body' => 'Please review the launch copy.']);

    AssignNoteUsersAction::run($assignedNote, [$user], assignedBy: null);
    MentionNoteUsersAction::run($mentionedNote, [$user], mentionedBy: null);

    test()->actingAs($user);

    Livewire::test(NotesInboxPage::class)
        ->assertSuccessful()
        ->assertSee('Follow up with legal before publish.')
        ->assertSee('Please review the launch copy.')
        ->assertSee((string) __('capell-notes::note.recent_notes'))
        ->assertSee('1');

    $counts = BuildUserAttentionCountsAction::run($user);

    expect($counts->mentions)->toBe(0)
        ->and($mentionedNote->mentions()->whereMorphedTo('mentioned', $user)->first()->read_at)->not->toBeNull();
});

it('does not render another participant private note in the inbox', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $visibleNote = Note::factory()->create(['body' => 'Visible current user note.']);
    $privateNote = Note::factory()->private()->create(['body' => 'Private note for another user.']);

    AssignNoteUsersAction::run($visibleNote, [$user], assignedBy: null);
    AssignNoteUsersAction::run($privateNote, [$otherUser], assignedBy: null);

    test()->actingAs($user);

    Livewire::test(NotesInboxPage::class)
        ->assertSuccessful()
        ->assertSee('Visible current user note.')
        ->assertDontSee('Private note for another user.');
});

it('marks mentions read when a status filter displays new notes', function (): void {
    $user = User::factory()->create();
    $openNote = Note::factory()->create(['body' => 'Open mention for current user.']);

    MentionNoteUsersAction::run($openNote, [$user], mentionedBy: null);

    test()->actingAs($user);

    $component = Livewire::test(NotesInboxPage::class)
        ->set('statusFilter', 'open');

    $resolvedNote = Note::factory()->resolved()->create(['body' => 'Resolved mention for current user.']);

    MentionNoteUsersAction::run($resolvedNote, [$user], mentionedBy: null);

    $component
        ->call('setStatusFilter', 'resolved')
        ->assertSuccessful()
        ->assertSee('Resolved mention for current user.')
        ->assertDontSee('Open mention for current user.');

    expect($resolvedNote->mentions()->whereMorphedTo('mentioned', $user)->first()->read_at)->not->toBeNull();
});
