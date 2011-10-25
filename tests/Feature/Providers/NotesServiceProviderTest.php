<?php

declare(strict_types=1);

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\Core\Facades\CapellCore;
use Capell\Notes\Filament\Extenders\Page\CreateNoteResourceHeaderActionExtender;
use Capell\Notes\Providers\NotesServiceProvider;

it('does not tag the resource header action extender when the package is not installed', function (): void {
    $tagsProperty = new ReflectionProperty(app(), 'tags');
    $originalTags = $tagsProperty->getValue(app());
    throw_unless(is_array($originalTags), RuntimeException::class, 'Expected the application container tags to be an array.');

    $tags = $originalTags;
    $tags[ResourceHeaderActionExtender::TAG] = array_values(array_filter(
        (array) ($tags[ResourceHeaderActionExtender::TAG] ?? []),
        static fn (mixed $abstract): bool => $abstract !== CreateNoteResourceHeaderActionExtender::class,
    ));

    $tagsProperty->setValue(app(), $tags);
    CapellCore::forcePackageInstalled(NotesServiceProvider::$packageName, false);

    try {
        $provider = app()->getProvider(NotesServiceProvider::class);
        throw_unless($provider instanceof NotesServiceProvider, RuntimeException::class, 'Expected the Notes service provider to be loaded.');

        $provider->registeringPackage();

        $hasNoteExtender = false;

        foreach (app()->tagged(ResourceHeaderActionExtender::TAG) as $extender) {
            if ($extender instanceof CreateNoteResourceHeaderActionExtender) {
                $hasNoteExtender = true;
                break;
            }
        }

        expect($hasNoteExtender)->toBeFalse();
    } finally {
        $tagsProperty->setValue(app(), $originalTags);
        CapellCore::forcePackageInstalled(NotesServiceProvider::$packageName);
    }
});
