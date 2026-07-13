<?php

declare(strict_types=1);

namespace Capell\Notes\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class NotesAdminPageContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
