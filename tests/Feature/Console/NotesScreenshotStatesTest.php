<?php

declare(strict_types=1);

use Capell\Core\Models\Page;
use Capell\Notes\Actions\BuildUserAttentionCountsAction;
use Capell\Notes\Actions\BuildUserInboxNotesAction;
use Capell\Notes\Filament\Pages\NotesInboxPage;
use Capell\Notes\Models\Note;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

it('prepares visibly populated and empty inboxes for the capture administrator and restores demo content', function (): void {
    User::factory()->create(['email' => 'first@example.test']);
    $actor = User::factory()->create(['email' => 'capture@example.test']);
    Page::factory()->create();
    Gate::before(static fn (): bool => true);
    $this->actingAs($actor);

    $environment = [
        'CAPELL_SCREENSHOT_FIXTURE' => 'record-state',
        'CAPELL_SCREENSHOT_APP_PATH' => base_path(),
        'CAPELL_SCREENSHOT_USER_ADMIN_EMAIL' => $actor->email,
    ];
    $original = [];
    foreach ($environment as $key => $value) {
        $original[$key] = getenv($key);
        putenv($key . '=' . $value);
    }

    try {
        $this->artisan('capell:notes-demo', ['--force' => true])->assertSuccessful();
        expect(BuildUserInboxNotesAction::run($actor))->not->toBeEmpty()
            ->and(BuildUserAttentionCountsAction::run($actor)->assigned)->toBe(2);
        Livewire::test(NotesInboxPage::class)
            ->assertSee('[Notes demo] Confirm the revised sitemap copy before launch.')
            ->assertDontSee((string) __('capell-notes::note.empty_inbox'))
            ->assertSee('[Notes demo] Resolved editorial question kept for workflow proof.')
            ->call('setStatusFilter', 'open')
            ->assertSee('[Notes demo] Confirm the revised sitemap copy before launch.')
            ->assertDontSee('[Notes demo] Resolved editorial question kept for workflow proof.');

        $this->artisan('capell:notes-demo', ['--force' => true, '--empty' => true])->assertSuccessful();
        expect(BuildUserInboxNotesAction::run($actor))->toBeEmpty();
        Livewire::test(NotesInboxPage::class)
            ->assertSee((string) __('capell-notes::note.empty_inbox'))
            ->assertDontSee('[Notes demo] Confirm the revised sitemap copy before launch.');

        $this->artisan('capell:notes-demo', ['--force' => true])->assertSuccessful();
        expect(BuildUserInboxNotesAction::run($actor))->not->toBeEmpty();

        $ordinary = Note::factory()->create([
            'body' => 'Ordinary content must survive.',
            'author_type' => $actor->getMorphClass(),
            'author_id' => $actor->getKey(),
        ]);
        $count = Note::query()->count();
        $this->artisan('capell:notes-demo', ['--force' => true, '--empty' => true])->assertFailed();
        expect(Note::query()->count())->toBe($count)
            ->and($ordinary->fresh())->not->toBeNull();
    } finally {
        foreach ($original as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
    }
});

it('refuses empty inbox preparation outside the disposable screenshot environment', function (): void {
    $this->artisan('capell:notes-demo', ['--force' => true, '--empty' => true])->assertFailed();
});
