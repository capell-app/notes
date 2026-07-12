<?php

declare(strict_types=1);

namespace Capell\Notes\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RunsScheduledExtensionJob;

final class NotesReminderScheduleContribution implements ExtensionContribution, RunsScheduledExtensionJob
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }
}
