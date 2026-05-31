<?php

declare(strict_types=1);

use Capell\Admin\Filament\Resources\Pages\Pages\EditPage;
use Capell\Admin\Filament\Resources\Pages\Pages\ListPages;
use Capell\Core\Models\Page;
use Capell\Notes\Filament\Extenders\Page\CreateNoteResourceHeaderActionExtender;
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
