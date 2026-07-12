<?php

declare(strict_types=1);

namespace Capell\Notes\Manifest;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;

final class NotesHealthContribution implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
