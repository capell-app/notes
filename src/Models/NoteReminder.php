<?php

declare(strict_types=1);

namespace Capell\Notes\Models;

use Capell\Notes\Casts\NoteReminderInstant;
use Capell\Notes\Database\Factories\NoteReminderFactory;
use Capell\Notes\Enums\NoteReminderRecurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $schedule_version
 * @property CarbonImmutable|null $delivery_failed_at
 * @property int $note_id
 * @property CarbonImmutable|null $due_at
 * @property string $timezone
 * @property NoteReminderRecurrence $recurrence
 * @property CarbonImmutable|null $next_due_at
 * @property CarbonImmutable|null $last_notified_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 *
 * @method static NoteReminderFactory factory($count = null, $state = [])
 */
class NoteReminder extends Model
{
    /** @use HasFactory<NoteReminderFactory> */
    use HasFactory;

    public const int UTC_SCHEDULE_VERSION = 3;

    protected static string $factory = NoteReminderFactory::class;

    /** @var list<string> */
    protected $fillable = [
        'note_id',
        'due_at',
        'timezone',
        'schedule_version',
        'delivery_failed_at',
        'recurrence',
        'next_due_at',
        'last_notified_at',
        'completed_at',
        'cancelled_at',
    ];

    /**
     * @return BelongsTo<Note, $this>
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeDueBefore(Builder $query, CarbonImmutable $until, bool $inclusive = false): Builder
    {
        return $this->dueWindow($query, null, $until, $inclusive);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeDueBetween(Builder $query, CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        return $this->dueWindow($query, $from, $until, true);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'schedule_version' => 'integer',
            'delivery_failed_at' => NoteReminderInstant::class,
            'due_at' => NoteReminderInstant::class,
            'recurrence' => NoteReminderRecurrence::class,
            'next_due_at' => NoteReminderInstant::class,
            'last_notified_at' => NoteReminderInstant::class,
            'completed_at' => NoteReminderInstant::class,
            'cancelled_at' => NoteReminderInstant::class,
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    private function dueWindow(Builder $query, ?CarbonImmutable $from, CarbonImmutable $until, bool $inclusive): Builder
    {
        return $query->where(function (Builder $versions) use ($from, $until, $inclusive): void {
            foreach ([false, true] as $utc) {
                $timezone = $utc ? 'UTC' : config()->string('app.timezone', 'UTC');
                $start = $from?->setTimezone($timezone)->toDateTimeString();
                $end = $until->setTimezone($timezone)->toDateTimeString();
                $versions->orWhere(function (Builder $version) use ($utc, $start, $end, $inclusive): void {
                    $version->where('schedule_version', $utc ? '>=' : '<', self::UTC_SCHEDULE_VERSION)
                        ->where(function (Builder $due) use ($start, $end, $inclusive): void {
                            foreach (['next_due_at', 'due_at'] as $column) {
                                $due->orWhere(function (Builder $occurrence) use ($column, $start, $end, $inclusive): void {
                                    if ($column === 'due_at') {
                                        $occurrence->whereNull('next_due_at');
                                    }

                                    if ($start !== null) {
                                        $occurrence->where($column, '>=', $start);
                                    }

                                    $occurrence->where($column, $inclusive ? '<=' : '<', $end);
                                });
                            }
                        });
                });
            }
        });
    }
}
