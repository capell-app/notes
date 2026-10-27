<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Enums\NoteReminderRecurrence;
use Capell\Notes\Models\NoteReminder;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static ?CarbonImmutable run(NoteReminder $reminder, CarbonImmutable $now) */
final class CalculateNextNoteReminderDueAction
{
    use AsFake;
    use AsObject;

    public function handle(NoteReminder $reminder, CarbonImmutable $now): ?CarbonImmutable
    {
        if ($reminder->recurrence === NoteReminderRecurrence::None) {
            return null;
        }

        $timezone = $reminder->schedule_version >= 2 ? $reminder->timezone : config()->string('app.timezone', 'UTC');
        $anchor = $reminder->schedule_version >= 2
            ? ($reminder->due_at ?? $reminder->next_due_at)
            : ($reminder->next_due_at ?? $reminder->due_at);
        if (! $anchor instanceof CarbonImmutable) {
            return null;
        }

        $anchor = $anchor->setTimezone($timezone);
        $localNow = $now->setTimezone($timezone);
        $period = max(1, (int) match ($reminder->recurrence) {
            NoteReminderRecurrence::Daily => $anchor->diffInDays($localNow),
            NoteReminderRecurrence::Weekly => $anchor->diffInWeeks($localNow),
            NoteReminderRecurrence::Monthly => $anchor->diffInMonths($localNow),
            NoteReminderRecurrence::Yearly => $anchor->diffInYears($localNow),
        });

        do {
            // Always calculate from the original wall time: DST gaps and short months
            // must not permanently shift subsequent occurrences.
            $next = match ($reminder->recurrence) {
                NoteReminderRecurrence::Daily => $anchor->addDays($period),
                NoteReminderRecurrence::Weekly => $anchor->addWeeks($period),
                NoteReminderRecurrence::Monthly => $anchor->addMonthsNoOverflow($period),
                NoteReminderRecurrence::Yearly => $anchor->addYearsNoOverflow($period),
            };
            $period++;
        } while ($next->lessThanOrEqualTo($now));

        return $next->setTimezone($reminder->schedule_version >= NoteReminder::UTC_SCHEDULE_VERSION
            ? 'UTC' : config()->string('app.timezone', 'UTC'));
    }
}
