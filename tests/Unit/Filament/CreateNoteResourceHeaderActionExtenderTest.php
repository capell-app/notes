<?php

declare(strict_types=1);

use Capell\Admin\Filament\Resources\Pages\Pages\EditPage;
use Capell\Admin\Filament\Resources\Pages\Pages\ListPages;
use Capell\Core\Models\Page;
use Capell\Notes\Enums\NoteVisibility;
use Capell\Notes\Filament\Extenders\Page\CreateNoteResourceHeaderActionExtender;
use Capell\Notes\Models\Note;
use Capell\Notes\Support\NotesManager;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('contributes an add note action to page edit screens', function (): void {
    $extender = new CreateNoteResourceHeaderActionExtender;

    expect($extender->supports(EditPage::class))->toBeTrue()
        ->and($extender->supports(ListPages::class))->toBeFalse();

    $actions = $extender->actions();

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->getName())->toBe('createNote');
});

it('authorizes note creation against the edited page update policy', function (): void {
    Gate::define('update', fn (mixed $actor, Page $record): bool => false);

    $page = Page::factory()->create();
    $action = (new CreateNoteResourceHeaderActionExtender)->actions()[0];

    expect($action->record($page)->isAuthorized())->toBeFalse();
});

it('does not authorize the header action for unsupported note subjects', function (): void {
    Gate::before(fn (mixed $actor, string $ability): ?bool => $ability === 'update' ? true : null);

    $action = (new CreateNoteResourceHeaderActionExtender)->actions()[0];

    expect($action->record(new Note)->isAuthorized())->toBeFalse();
});

it('creates a note from the page header action with selected assignees and mentions', function (): void {
    Gate::before(fn (mixed $actor, string $ability): ?bool => $ability === 'update' ? true : null);

    $notes = resolve(NotesManager::class);
    $notes->registerSubject(Page::class);

    $author = User::factory()->create(['name' => 'Author']);
    $assignee = User::factory()->create(['name' => 'Assignee']);
    $mentioned = User::factory()->create(['email' => 'mentioned@example.test']);
    $page = Page::factory()->create();
    $action = (new CreateNoteResourceHeaderActionExtender)->actions()[0];
    $closure = $action->getActionFunction();

    $this->actingAs($author);

    throw_if(! $closure instanceof Closure, RuntimeException::class, 'Expected create note action closure.');

    $action->evaluate($closure, [
        'record' => $page,
        'data' => [
            'body' => 'Please check this draft.',
            'visibility' => NoteVisibility::Private->value,
            'assignee_ids' => [$assignee->getKey()],
            'mention_ids' => [$mentioned->getKey()],
        ],
    ]);

    $note = Note::query()->firstOrFail();

    expect($note->subject)->not->toBeNull()
        ->and($note->subject?->is($page))->toBeTrue()
        ->and($note->author)->not->toBeNull()
        ->and($note->author?->is($author))->toBeTrue()
        ->and($note->body)->toBe('Please check this draft.')
        ->and($note->visibility)->toBe(NoteVisibility::Private)
        ->and($note->assignments()->whereMorphedTo('assignee', $assignee)->exists())->toBeTrue()
        ->and($note->mentions()->whereMorphedTo('mentioned', $mentioned)->exists())->toBeTrue();
});
