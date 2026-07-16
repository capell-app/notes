<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Enums\NoteReminderRecurrence;
use Capell\Notes\Models\NoteAssignment;
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
        $now ??= CarbonImmutable::now();
        $sent = 0;

        NoteReminder::query()
            ->with(['note.assignments.assignee'])
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->where('next_due_at', '<=', $now)
                    ->orWhere(function (Builder $fallbackQuery) use ($now): void {
                        $fallbackQuery
                            ->whereNull('next_due_at')
                            ->where('due_at', '<=', $now);
                    });
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('last_notified_at')
                    ->orWhereColumn('last_notified_at', '<', 'next_due_at');
            })
            ->oldest('next_due_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (NoteReminder $reminder) use ($now, &$sent): void {
                $note = $reminder->note;

                if ($note === null) {
                    return;
                }

                $note->assignments
                    ->filter(static fn (NoteAssignment $assignment): bool => $assignment->completed_at === null)
                    ->each(function (NoteAssignment $assignment) use ($note, &$sent): void {
                        $assignee = $assignment->assignee;

                        if ($assignee instanceof Model && method_exists($assignee, 'notify') && CanViewNoteAction::run($note, $assignee)) {
                            $assignee->notify(new NoteAttentionNotification($note, 'reminder'));
                            $sent++;
                        }
                    });

                $reminder->forceFill([
                    'last_notified_at' => $now,
                    'next_due_at' => $this->nextDueAt($reminder, $now),
                ])->save();
            });

        return $sent;
    }

    private function nextDueAt(NoteReminder $reminder, CarbonImmutable $now): ?CarbonImmutable
    {
        return match ($reminder->recurrence) {
            NoteReminderRecurrence::Daily => $now->addDay(),
            NoteReminderRecurrence::Weekly => $now->addWeek(),
            NoteReminderRecurrence::Monthly => $now->addMonth(),
            NoteReminderRecurrence::Yearly => $now->addYear(),
            NoteReminderRecurrence::None => $reminder->next_due_at,
        };
    }
}
