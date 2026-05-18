<?php

declare(strict_types=1);

namespace Capell\Notes\Models;

use Capell\Notes\Database\Factories\NoteFactory;
use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Enums\NoteVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * @property int $id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $author_type
 * @property int|null $author_id
 * @property string $body
 * @property NoteStatus $status
 * @property NoteVisibility $visibility
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $archived_at
 *
 * @method static NoteFactory factory($count = null, $state = [])
 */
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    protected static string $factory = NoteFactory::class;

    /** @var list<string> */
    protected $fillable = [
        'subject_type',
        'subject_id',
        'author_type',
        'author_id',
        'body',
        'status',
        'visibility',
        'resolved_at',
        'archived_at',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<NoteAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(NoteAssignment::class);
    }

    /**
     * @return HasMany<NoteMention, $this>
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(NoteMention::class);
    }

    /**
     * @return HasOne<NoteReminder, $this>
     */
    public function reminder(): HasOne
    {
        return $this->hasOne(NoteReminder::class);
    }

    #[Override]
    protected static function booted(): void
    {
        static::deleting(function (Note $note): void {
            $note->assignments()->delete();
            $note->mentions()->delete();
            $note->reminder()->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => NoteStatus::class,
            'visibility' => NoteVisibility::class,
            'resolved_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
