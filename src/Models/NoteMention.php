<?php

declare(strict_types=1);

namespace Capell\Notes\Models;

use Capell\Notes\Database\Factories\NoteMentionFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * @property int $id
 * @property int $note_id
 * @property string $mentioned_type
 * @property int $mentioned_id
 * @property string|null $mentioned_by_type
 * @property int|null $mentioned_by_id
 * @property CarbonImmutable|null $read_at
 *
 * @method static NoteMentionFactory factory($count = null, $state = [])
 */
class NoteMention extends Model
{
    /** @use HasFactory<NoteMentionFactory> */
    use HasFactory;

    protected static string $factory = NoteMentionFactory::class;

    /** @var list<string> */
    protected $fillable = [
        'note_id',
        'mentioned_type',
        'mentioned_id',
        'mentioned_by_type',
        'mentioned_by_id',
        'read_at',
    ];

    /**
     * @return BelongsTo<Note, $this>
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function mentioned(): MorphTo
    {
        return $this->morphTo();
    }

    public function mentionedBy(): MorphTo
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
            'read_at' => 'immutable_datetime',
        ];
    }
}
