<?php

declare(strict_types=1);

namespace Capell\Notes\Models;

use Capell\Core\Concerns\HasCapellTable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * One earlier body of a note, kept when the note was edited.
 *
 * @property int $id
 * @property int $note_id
 * @property string $body
 * @property string|null $edited_by_type
 * @property int|null $edited_by_id
 * @property CarbonImmutable|null $created_at
 */
class NoteRevision extends Model
{
    use HasCapellTable;

    /** @var list<string> */
    protected $fillable = [
        'note_id',
        'body',
        'edited_by_type',
        'edited_by_id',
    ];

    /**
     * @return BelongsTo<Note, $this>
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function editedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'created_at' => 'immutable_datetime',
        ];
    }
}
