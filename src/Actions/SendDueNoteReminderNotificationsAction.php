<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Enums\NoteReminderRecurrence;
use Capell\Notes\Models\NoteReminder;
use Capell\Notes\Notifications\NoteAttentionNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static int run(?CarbonImmutable $now = null, int $limit = 100)
 */
final class SendDueNoteReminderNotificationsAction
{
    use AsFake;
    use AsObject;

    public function handle(?CarbonImmutable $now = null, int $limit = 100): int
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone(config()->string('app.timezone', 'UTC'));
        $sent = 0;

        NoteReminder::query()
            ->with(['note.assignments.assignee'])
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->dueBefore($now, inclusive: true)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('last_notified_at')
                    ->orWhereColumn('last_notified_at', '<', 'next_due_at');
            })
            ->oldest('delivery_failed_at')
            ->oldest('next_due_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (NoteReminder $reminder) use ($now, &$sent): void {
                $note = $reminder->note;

                if ($note === null) {
                    return;
                }

                $recipients = ResolveNoteReminderRecipientsAction::run($note);
                if ($recipients === []) {
                    if ($reminder->delivery_failed_at === null) {
                        $author = $note->author;
                        if ($author instanceof Model && method_exists($author, 'notify') && CanViewNoteAction::run($note, $author)) {
                            $author->notify(new NoteAttentionNotification($note, 'reminder_unsent'));
                        }
                    }

                    $reminder->forceFill(['delivery_failed_at' => $now])->save();

                    return;
                }

                foreach ($recipients as $recipient) {
                    if (! method_exists($recipient, 'notify')) {
                        continue;
                    }

                    $recipient->notify(new NoteAttentionNotification($note, 'reminder'));
                    $sent++;
                }

                $reminder->forceFill([
                    'last_notified_at' => $now,
                    'next_due_at' => CalculateNextNoteReminderDueAction::run($reminder, $now),
                    'completed_at' => $reminder->recurrence === NoteReminderRecurrence::None ? $now : null,
                    'delivery_failed_at' => null,
                ])->save();
            });

        return $sent;
    }
}
