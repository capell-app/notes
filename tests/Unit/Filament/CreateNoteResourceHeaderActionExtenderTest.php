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
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

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

it('interprets a reminder wall time in its selected timezone through the header action', function (): void {
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
    $author = User::factory()->create();
    test()->actingAs($author);
    $action = (new CreateNoteResourceHeaderActionExtender)->actions()[0];
    $action->evaluate($action->getActionFunction(), [
        'record' => $author,
        'data' => [
            'body' => 'Timezone follow-up', 'visibility' => NoteVisibility::Private->value,
            'assignee_ids' => [$author->id], 'reminder_due_at' => '2026-07-01 09:00:00',
            'reminder_timezone' => 'America/New_York',
        ],
    ]);
    expect(Note::query()->firstOrFail()->reminder?->due_at?->utc()->format('Y-m-d H:i'))->toBe('2026-07-01 13:00');
});

it('rejects an invalid timezone from the header action without creating a note', function (): void {
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
    $author = User::factory()->create();
    test()->actingAs($author);
    $action = (new CreateNoteResourceHeaderActionExtender)->actions()[0];
    expect(fn () => $action->evaluate($action->getActionFunction(), [
        'record' => $author,
        'data' => [
            'body' => 'Invalid timezone follow-up', 'visibility' => NoteVisibility::Private->value,
            'assignee_ids' => [$author->id], 'reminder_due_at' => '2026-07-01 09:00:00',
            'reminder_timezone' => 'Invalid/Zone',
        ],
    ]))->toThrow(ValidationException::class)
        ->and(Note::query()->count())->toBe(0);
});

it('preserves entered wall time through schema dehydration before parsing the selected timezone', function (string $appTimezone, string $timezone, string $wallTime, string $utc): void {
    config(['app.timezone' => $appTimezone]);
    $originalTimezone = date_default_timezone_get();
    date_default_timezone_set($appTimezone);

    try {
        Gate::before(static fn (): bool => true);
        $author = User::factory()->create();
        test()->actingAs($author);
        $host = new class extends Component implements HasSchemas
        {
            use InteractsWithSchemas;

            /** @var array<string, mixed> */
            public array $formData = [];
        };
        $action = (new CreateNoteResourceHeaderActionExtender)->actions()[0]->record($author);
        $schema = $action->getSchema(Schema::make($host)->model($author)->statePath('formData'));
        throw_unless($schema instanceof Schema, LogicException::class);
        $schema->fill();
        // These are the raw wall-clock strings sent by the browser picker.
        $host->formData = [
            'body' => 'Dehydrated timezone follow-up',
            'visibility' => NoteVisibility::Private->value,
            'assignee_ids' => [$author->id],
            'mention_ids' => [],
            'reminder_due_at' => $wallTime,
            'reminder_timezone' => $timezone,
            'reminder_recurrence' => 'none',
        ];
        $data = $schema->getState();
        $dehydratedWallTime = $data['reminder_due_at'];
        throw_unless(is_string($dehydratedWallTime), LogicException::class);
        expect(substr($dehydratedWallTime, 0, 16))->toBe(substr($wallTime, 0, 16));
        $action->evaluate($action->getActionFunction(), ['record' => $author, 'data' => $data]);
        expect(Note::query()->firstOrFail()->reminder?->due_at?->utc()->format('Y-m-d H:i'))->toBe($utc);
    } finally {
        date_default_timezone_set($originalTimezone);
    }
})->with([
    'application spring gap' => ['Europe/London', 'America/New_York', '2026-03-29 01:30:00', '2026-03-29 05:30'],
    'application autumn overlap' => ['Europe/London', 'America/New_York', '2026-10-25 01:30:00', '2026-10-25 05:30'],
    'selected spring gap' => ['UTC', 'Europe/London', '2026-03-29 01:30:00', '2026-03-29 01:30'],
    'summer conversion once' => ['Europe/London', 'America/New_York', '2026-07-01 09:00:00', '2026-07-01 13:00'],
]);

it('searches and restores only permitted assignees through the real form selectors', function (): void {
    $author = User::factory()->create(['name' => 'Author']);
    $permitted = User::factory()->create(['name' => 'Candidate permitted']);
    $denied = User::factory()->create(['name' => 'Candidate denied']);
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? ! $user->is($denied) : null);
    test()->actingAs($author);
    $host = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;

        /** @var array<string, mixed> */
        public array $formData = [];
    };
    $action = (new CreateNoteResourceHeaderActionExtender)->actions()[0]->record($author);
    $schema = $action->getSchema(Schema::make($host)->model($author)->statePath('formData'));
    throw_unless($schema instanceof Schema, LogicException::class);
    $schema->fill(['assignee_ids' => [$permitted->id, $denied->id], 'mention_ids' => [$permitted->id, $denied->id]]);
    $selectors = collect($schema->getComponents())->filter(fn ($component): bool => $component instanceof Select && in_array($component->getName(), ['assignee_ids', 'mention_ids'], true));
    expect($selectors)->toHaveCount(2);
    foreach ($selectors as $component) {
        throw_unless($component instanceof Select, LogicException::class);
        expect($component->getSearchResults('Candidate'))->toBe([$permitted->id => $permitted->name])
            ->and($component->getOptionLabels())->toBe([$permitted->id => $permitted->name]);
    }
});
