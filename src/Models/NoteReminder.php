<?php

declare(strict_types=1);

namespace Capell\Notes\Models;

use Capell\Notes\Database\Factories\NoteReminderFactory;
use Capell\Notes\Enums\NoteReminderRecurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
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

    protected static string $factory = NoteReminderFactory::class;

    /** @var list<string> */
    protected $fillable = [
        'note_id',
        'due_at',
        'timezone',
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
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
            'recurrence' => NoteReminderRecurrence::class,
            'next_due_at' => 'immutable_datetime',
            'last_notified_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
