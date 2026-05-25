<?php

declare(strict_types=1);

use Capell\Admin\Filament\Resources\Pages\Pages\EditPage;
use Capell\Admin\Filament\Resources\Pages\Pages\ListPages;
use Capell\Notes\Filament\Extenders\Page\CreateNoteResourceHeaderActionExtender;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('contributes an add note action to page edit screens', function (): void {
    $extender = new CreateNoteResourceHeaderActionExtender;

    expect($extender->supports(EditPage::class))->toBeTrue()
        ->and($extender->supports(ListPages::class))->toBeFalse();

    $actions = $extender->actions();

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->getName())->toBe('createNote');
});
