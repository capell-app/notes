<?php

declare(strict_types=1);

namespace Capell\Notes\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class NotesConsoleCommandsContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
