<?php

declare(strict_types=1);

use Capell\Notes\Actions\AssignNoteUsersAction;
use Capell\Notes\Actions\BuildUserAttentionCountsAction;
use Capell\Notes\Actions\MentionNoteUsersAction;
use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Filament\Pages\NotesInboxPage;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteMention;
use Capell\Notes\Models\NoteReminder;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('renders inbox notes and marks displayed mentions read after preserving initial counts', function (): void {
    Gate::before(static fn (User $actor, string $ability): ?bool => $ability === 'update' ? true : null);
    $user = User::factory()->create(['name' => 'Editor One']);
    $assignedNote = Note::factory()->create(['body' => 'Follow up with legal before publish.']);
    $mentionedNote = Note::factory()->create(['body' => 'Please review the launch copy.']);

    AssignNoteUsersAction::run($assignedNote, [$user], assignedBy: $user);
    MentionNoteUsersAction::run($mentionedNote, [$user], mentionedBy: $user);

    test()->actingAs($user);

    Livewire::test(NotesInboxPage::class)
        ->assertSuccessful()
        ->assertSee('Follow up with legal before publish.')
        ->assertSee('Please review the launch copy.')
        ->assertSee((string) __('capell-notes::note.recent_notes'))
        ->assertSee('1');

    $counts = BuildUserAttentionCountsAction::run($user);
    $mention = $mentionedNote->mentions()->whereMorphedTo('mentioned', $user)->first();
    throw_unless($mention instanceof NoteMention, RuntimeException::class, 'Expected note mention to exist.');

    expect($counts->mentions)->toBe(0)
        ->and($mention->read_at)->not->toBeNull();
});

it('does not render another participant private note in the inbox', function (): void {
    Gate::before(static fn (User $actor, string $ability): ?bool => $ability === 'update' ? true : null);
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $visibleNote = Note::factory()->create(['body' => 'Visible current user note.']);
    $privateNote = Note::factory()->private()->create(['body' => 'Private note for another user.']);

    AssignNoteUsersAction::run($visibleNote, [$user], assignedBy: $user);
    AssignNoteUsersAction::run($privateNote, [$otherUser], assignedBy: $user);

    test()->actingAs($user);

    Livewire::test(NotesInboxPage::class)
        ->assertSuccessful()
        ->assertSee('Visible current user note.')
        ->assertDontSee('Private note for another user.');
});

it('marks mentions read when a status filter displays new notes', function (): void {
    Gate::before(static fn (User $actor, string $ability): ?bool => $ability === 'update' ? true : null);
    $user = User::factory()->create();
    $openNote = Note::factory()->create(['body' => 'Open mention for current user.']);

    MentionNoteUsersAction::run($openNote, [$user], mentionedBy: $user);

    test()->actingAs($user);

    $component = Livewire::test(NotesInboxPage::class)
        ->set('statusFilter', 'open');

    $resolvedNote = Note::factory()->resolved()->create(['body' => 'Resolved mention for current user.']);

    MentionNoteUsersAction::run($resolvedNote, [$user], mentionedBy: $user);

    $component
        ->call('setStatusFilter', 'resolved')
        ->assertSuccessful()
        ->assertSee('Resolved mention for current user.')
        ->assertDontSee('Open mention for current user.');

    expect($resolvedNote->mentions()->whereMorphedTo('mentioned', $user)->first()->read_at)->not->toBeNull();
});

it('delegates note lifecycle actions from the inbox', function (): void {
    Gate::before(static fn (User $actor, string $ability): ?bool => $ability === 'update' ? true : null);
    $user = User::factory()->create();
    $assignedNote = Note::factory()->create(['body' => 'Lifecycle controls are available.']);

    AssignNoteUsersAction::run($assignedNote, [$user], assignedBy: $user);

    test()->actingAs($user);

    Livewire::test(NotesInboxPage::class)
        ->assertSuccessful()
        ->assertSee((string) __('capell-notes::note.actions.resolve'))
        ->assertSee((string) __('capell-notes::note.actions.complete_assignment'))
        ->call('resolveNote', $assignedNote->id);

    expect($assignedNote->refresh()->status->value)->toBe('resolved')
        ->and($assignedNote->resolved_at)->not->toBeNull();

    Livewire::test(NotesInboxPage::class)
        ->set('statusFilter', 'resolved')
        ->assertSee((string) __('capell-notes::note.actions.reopen'))
        ->call('reopenNote', $assignedNote->id)
        ->assertSuccessful();

    Livewire::test(NotesInboxPage::class)
        ->assertSee((string) __('capell-notes::note.actions.complete_assignment'))
        ->call('completeAssignment', $assignedNote->id)
        ->assertSuccessful();

    $assignment = $assignedNote->assignments()->whereMorphedTo('assignee', $user)->first();

    expect($assignedNote->refresh()->status->value)->toBe('open')
        ->and($assignment?->completed_at)->not->toBeNull();
});

it('pages through all permitted assignments and reads full instructions before completing older work', function (): void {
    $access = (object) ['denied' => null];
    Gate::before(static fn (User $actor, string $ability, array $arguments): ?bool => $ability === 'update'
        ? ($access->denied === null || ! $arguments[0]->is($access->denied)) : null);
    $user = User::factory()->create();
    $subject = User::factory()->create();
    $denied = User::factory()->create();
    $notes = Note::factory()->count(30)->create([
        'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id,
        'body' => str_repeat('Instructions ', 30) . 'Final instruction <script>secret()</script>',
    ]);
    foreach ($notes as $note) {
        AssignNoteUsersAction::run($note, [$user], assignedBy: $user);
    }

    $hidden = Note::factory()->count(4)->create([
        'subject_type' => $denied->getMorphClass(), 'subject_id' => $denied->id,
        'body' => 'Denied subject sentinel',
    ]);
    foreach ($hidden as $note) {
        AssignNoteUsersAction::run($note, [$user], assignedBy: $user);
    }

    $access->denied = $denied;
    test()->actingAs($user);

    $component = Livewire::test(NotesInboxPage::class)
        ->assertSee('Final instruction &lt;script&gt;secret()&lt;/script&gt;', false)
        ->assertDontSee('<script>secret()</script>', false)
        ->assertDontSee('Denied subject sentinel');
    $page = $component->instance();
    throw_unless($page instanceof NotesInboxPage, LogicException::class);
    expect($page->inboxNotes())->toHaveCount(25)
        ->and($page->counts()->assigned)->toBe(30);
    $component->call('setAttentionFilter', 'assigned')->assertSet('attentionFilter', 'assigned')
        ->call('setAttentionFilter', 'unsupported')->assertSet('attentionFilter', 'assigned')
        ->call('setStatusFilter', 'unsupported')->assertSet('statusFilter', 'all');
    $firstIds = $page->inboxNotes()->modelKeys();
    $component->call('nextPage')->assertSuccessful();
    $page = $component->instance();
    throw_unless($page instanceof NotesInboxPage, LogicException::class);
    $secondIds = $page->inboxNotes()->modelKeys();
    expect($secondIds)->toHaveCount(5)
        ->and(array_unique([...$firstIds, ...$secondIds]))->toHaveCount(30);
    $component->call('completeAssignment', $notes->firstOrFail()->id)->assertSuccessful();
    expect($notes->firstOrFail()->assignments()->firstOrFail()->completed_at)->not->toBeNull();
    $component->call('completeAssignment', $hidden->firstOrFail()->id)->assertSuccessful();
    expect($hidden->firstOrFail()->assignments()->firstOrFail()->completed_at)->toBeNull();
    $component->call('previousPage')->assertSet('inboxPage', 1)->assertDontSee('Denied subject sentinel');
});

it('shows lifecycle buttons only for authors and active assignees and denies forged calls', function (string $role, bool $allowed): void {
    // The fixture-wide allow-all gate would bypass NotePolicy, so replace it.
    Gate::before(static fn (User $actor, string $ability): ?bool => $ability === 'update' ? true : null);
    $user = User::factory()->create();
    $note = Note::factory()->create($role === 'author' ? [
        'author_type' => $user->getMorphClass(), 'author_id' => $user->id,
    ] : []);
    if (in_array($role, ['active', 'completed'], true)) {
        AssignNoteUsersAction::run($note, [$user], assignedBy: $note->author);
    }

    if ($role === 'completed') {
        $note->assignments()->update(['completed_at' => now()]);
    }

    if (! $allowed) {
        MentionNoteUsersAction::run($note, [$user], mentionedBy: $note->author);
    }

    test()->actingAs($user);
    $component = Livewire::test(NotesInboxPage::class)->assertSuccessful();
    if ($role === 'active') {
        $component->assertSee('wire:click="completeAssignment(' . $note->id . ')"', false);
    } else {
        $component->assertDontSee('wire:click="completeAssignment(' . $note->id . ')"', false);
    }

    $button = 'wire:click="resolveNote(' . $note->id . ')"';
    if ($allowed) {
        $component->assertSee($button, false)->call('resolveNote', $note->id)->assertSuccessful()
            ->assertSee('wire:click="reopenNote(' . $note->id . ')"', false)
            ->call('reopenNote', $note->id)->assertSuccessful();
    } else {
        $component->assertDontSee($button, false)->call('resolveNote', $note->id)->assertForbidden();
        $note->forceFill(['status' => NoteStatus::Resolved])->save();
        Livewire::test(NotesInboxPage::class)
            ->assertDontSee('wire:click="reopenNote(' . $note->id . ')"', false)
            ->call('reopenNote', $note->id)->assertForbidden();
    }
})->with(['author' => ['author', true], 'active assignee' => ['active', true], 'completed assignee' => ['completed', false], 'mention' => ['mention', false]]);

it('shows the effective reminder instant and an actionable unsent state to its authorised owner', function (int $version): void {
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
    $user = User::factory()->create();
    $note = Note::factory()->private()->create(['author_type' => $user->getMorphClass(), 'author_id' => $user->id]);
    NoteReminder::factory()->for($note)->create([
        'due_at' => '2026-07-01 13:00:00', 'next_due_at' => '2026-07-01 13:00:00',
        'timezone' => 'America/New_York', 'schedule_version' => $version, 'delivery_failed_at' => now(),
    ]);
    test()->actingAs($user);
    Livewire::test(NotesInboxPage::class)
        ->assertSee('2026-07-01 09:00 EDT')
        ->assertSee('2026-07-01 13:00 UTC')
        ->assertSee((string) __('capell-notes::note.reminder_unsent'));
})->with([2, 3]);
