<?php

declare(strict_types=1);

use Capell\Notes\Actions\UpdateNoteBodyAction;
use Capell\Notes\Models\Note;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

beforeEach(function (): void {
    // Everyone may update the subject, so only the note's own policy decides who can edit.
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
});

it('keeps each replaced body as encrypted edit history, newest first', function (): void {
    // Bug caught: an edit that overwrote the body lost what the note used to say, or stored the old text unencrypted.
    $author = User::factory()->create();
    $note = Note::factory()->create([
        'author_type' => $author->getMorphClass(),
        'author_id' => $author->getKey(),
        'body' => 'First draft',
    ]);

    UpdateNoteBodyAction::run($note, 'Second draft', $author);
    UpdateNoteBodyAction::run($note->refresh(), 'Final wording', $author);

    $revisions = $note->refresh()->revisions()->with('editedBy')->get();

    expect($note->body)->toBe('Final wording')
        ->and($revisions->pluck('body')->all())->toBe(['Second draft', 'First draft'])
        ->and($revisions->first()->editedBy->is($author))->toBeTrue()
        ->and(DB::table('note_revisions')->pluck('body')->implode(' '))->not->toContain('First draft')->not->toContain('Second draft');
});

it('does not record a revision when the body is unchanged', function (): void {
    // Bug caught: saving without a change fills the history with identical entries.
    $author = User::factory()->create();
    $note = Note::factory()->create([
        'author_type' => $author->getMorphClass(),
        'author_id' => $author->getKey(),
        'body' => 'Same',
    ]);

    UpdateNoteBodyAction::run($note, 'Same', $author);

    expect($note->revisions()->count())->toBe(0);
});

it('refuses an edit from someone other than the author and leaves the note untouched', function (): void {
    // Bug caught: any viewer rewriting another person's note, with the history hiding who changed it.
    $author = User::factory()->create();
    $other = User::factory()->create();
    $note = Note::factory()->create([
        'author_type' => $author->getMorphClass(),
        'author_id' => $author->getKey(),
        'body' => 'Original',
    ]);

    expect(fn (): Note => UpdateNoteBodyAction::run($note, 'Hijacked', $other))->toThrow(AuthorizationException::class);
    expect($note->refresh()->body)->toBe('Original')
        ->and($note->revisions()->count())->toBe(0);
});

it('removes the history when the note is deleted', function (): void {
    // Bug caught: orphaned encrypted revisions surviving after the note they belong to is deleted.
    $author = User::factory()->create();
    $note = Note::factory()->create([
        'author_type' => $author->getMorphClass(),
        'author_id' => $author->getKey(),
        'body' => 'Before',
    ]);
    UpdateNoteBodyAction::run($note, 'After', $author);

    $note->delete();

    expect(DB::table('note_revisions')->count())->toBe(0);
});
