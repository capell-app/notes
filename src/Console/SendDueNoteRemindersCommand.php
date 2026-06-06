<?php

declare(strict_types=1);

namespace Capell\Notes\Console;

use Capell\Notes\Actions\SendDueNoteReminderNotificationsAction;
use Illuminate\Console\Command;

final class SendDueNoteRemindersCommand extends Command
{
    protected $signature = 'capell:notes:send-due-reminders
        {--limit= : Maximum reminder notifications to send in one run}';

    protected $description = 'Send due Capell Notes reminder notifications to active note assignees.';

    public function handle(): int
    {
        $sent = SendDueNoteReminderNotificationsAction::run(limit: $this->positiveIntOption('limit', 100));

        $this->components->info(sprintf('Sent %d note reminder notification%s.', $sent, $sent === 1 ? '' : 's'));

        return self::SUCCESS;
    }

    private function positiveIntOption(string $name, int $fallback): int
    {
        $value = $this->option($name);

        if (! is_numeric($value) || (int) $value < 1) {
            return max(1, $fallback);
        }

        return (int) $value;
    }
}
