<?php

declare(strict_types=1);

namespace Capell\Notes\Models;

use Capell\Notes\Database\Factories\NoteAssignmentFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $note_id
 * @property string $assignee_type
 * @property int $assignee_id
 * @property string|null $assigned_by_type
 * @property int|null $assigned_by_id
 * @property CarbonImmutable|null $completed_at
 *
 * @method static NoteAssignmentFactory factory($count = null, $state = [])
 */
class NoteAssignment extends Model
{
    /** @use HasFactory<NoteAssignmentFactory> */
    use HasFactory;

    protected static string $factory = NoteAssignmentFactory::class;

    /** @var list<string> */
    protected $fillable = [
        'note_id',
        'assignee_type',
        'assignee_id',
        'assigned_by_type',
        'assigned_by_id',
        'completed_at',
    ];

    /**
     * @return BelongsTo<Note, $this>
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function assignee(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
        ];
    }
}
