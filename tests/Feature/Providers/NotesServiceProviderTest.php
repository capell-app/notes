<?php

declare(strict_types=1);

use Capell\Admin\Filament\Resources\Pages\Pages\EditPage;
use Capell\Admin\Support\Schemas\AdminSchemaExtensionPipeline;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
use Filament\Actions\Action;

it('offers note creation only when the package is installed', function (bool $installed): void {
    InstalledRuntimeState::run(static function () use ($installed): void {
        $app = InstalledRuntimeTestCase::boot(InstalledRuntimeParity::manifest('notes'), $installed);
        $actions = $app->make(AdminSchemaExtensionPipeline::class)->resourceHeaderActions(EditPage::class);

        $names = array_map(static fn (Action $action): ?string => $action->getName(), $actions);
        expect(in_array('createNote', $names, true))->toBe($installed);
    });
})->with([false, true]);
