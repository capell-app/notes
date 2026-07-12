<?php

declare(strict_types=1);

namespace Capell\Notes\Filament\Pages;

use BackedEnum;
use Capell\Notes\Actions\BuildUserInboxNotesAction;
use Capell\Notes\Actions\CompleteNoteAssignmentAction;
use Capell\Notes\Actions\MarkNoteMentionsReadAction;
use Capell\Notes\Actions\ReopenNoteAction;
use Capell\Notes\Actions\ResolveNoteAction;
use Capell\Notes\Data\UserAttentionCountData;
use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteAssignment;
use Capell\Notes\Support\UserAttentionCountsCache;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Override;

final class NotesInboxPage extends Page
{
    public string $statusFilter = 'all';

    /** @var array{assigned: int, dueToday: int, overdue: int, mentions: int}|null */
    public ?array $initialCounts = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $slug = 'notes';

    protected static ?int $navigationSort = 80;

    protected string $view = 'capell-notes::filament.pages.notes-inbox';

    #[Override]
    public static function getNavigationLabel(): string
    {
        return (string) __('capell-notes::navigation.notes');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return (string) __('capell-admin::navigation.group_extensions');
    }

    public function mount(): void
    {
        $user = $this->user();

        if (! $user instanceof Model) {
            return;
        }

        $counts = $this->attentionCountsCache()->forUser($user);

        $this->initialCounts = [
            'assigned' => $counts->assigned,
            'dueToday' => $counts->dueToday,
            'overdue' => $counts->overdue,
            'mentions' => $counts->mentions,
        ];

        (new MarkNoteMentionsReadAction)->handle($user, $this->inboxNotes());
    }

    #[Override]
    public function getTitle(): string
    {
        return (string) __('capell-notes::note.inbox_title');
    }

    public function counts(): UserAttentionCountData
    {
        if ($this->initialCounts !== null) {
            return new UserAttentionCountData(
                assigned: $this->initialCounts['assigned'],
                dueToday: $this->initialCounts['dueToday'],
                overdue: $this->initialCounts['overdue'],
                mentions: $this->initialCounts['mentions'],
            );
        }

        $user = $this->user();

        if (! $user instanceof Model) {
            return new UserAttentionCountData;
        }

        return $this->attentionCountsCache()->forUser($user);
    }

    /**
     * @return Collection<int, Note>
     */
    public function inboxNotes(): Collection
    {
        $user = $this->user();

        if (! $user instanceof Model) {
            return new Collection;
        }

        return (new BuildUserInboxNotesAction)->handle(
            user: $user,
            status: $this->selectedStatus(),
        );
    }

    public function setStatusFilter(string $status): void
    {
        if (! in_array($status, ['all', NoteStatus::Open->value, NoteStatus::Resolved->value], true)) {
            return;
        }

        $this->statusFilter = $status;
        $user = $this->user();

        if ($user instanceof Model) {
            (new MarkNoteMentionsReadAction)->handle($user, $this->inboxNotes());
        }
    }

    public function resolveNote(int $noteId): void
    {
        $user = $this->user();
        $note = $this->noteVisibleToCurrentUser($noteId);

        if (! $user instanceof Model || ! $note instanceof Note || $note->status === NoteStatus::Resolved) {
            return;
        }

        (new ResolveNoteAction)->handle($note, $user);
        $this->forgetAttentionCounts();
    }

    public function reopenNote(int $noteId): void
    {
        $user = $this->user();
        $note = $this->noteVisibleToCurrentUser($noteId);

        if (! $user instanceof Model || ! $note instanceof Note || $note->status === NoteStatus::Open) {
            return;
        }

        (new ReopenNoteAction)->handle($note, $user);
        $this->forgetAttentionCounts();
    }

    public function completeAssignment(int $noteId): void
    {
        $user = $this->user();
        $note = $this->noteVisibleToCurrentUser($noteId);

        if (! $user instanceof Model || ! $note instanceof Note) {
            return;
        }

        (new CompleteNoteAssignmentAction)->handle($note, $user);
        $this->forgetAttentionCounts();
    }

    public function userLabel(?Model $user): string
    {
        if (! $user instanceof Model) {
            return (string) __('capell-notes::note.labels.unknown_user');
        }

        $label = $this->labelFromAttributes($user, ['name', 'email']);

        if ($label !== null) {
            return $label;
        }

        return (string) __('capell-notes::note.labels.record_fallback', [
            'record' => class_basename($user),
            'id' => $this->modelKeyLabel($user),
        ]);
    }

    public function subjectLabel(?Model $subject): string
    {
        if (! $subject instanceof Model) {
            return (string) __('capell-notes::note.labels.unknown_subject');
        }

        $label = $this->labelFromAttributes($subject, ['title', 'name', 'email']);

        if ($label !== null) {
            return $label;
        }

        return (string) __('capell-notes::note.labels.record_fallback', [
            'record' => class_basename($subject),
            'id' => $this->modelKeyLabel($subject),
        ]);
    }

    public function statusLabel(NoteStatus $status): string
    {
        return (string) __('capell-notes::note.status.' . $status->value);
    }

    public function excerpt(Note $note): string
    {
        return Str::limit($note->body, 220);
    }

    public function hasIncompleteAssignmentForCurrentUser(Note $note): bool
    {
        $user = $this->user();

        if (! $user instanceof Model) {
            return false;
        }

        $userKey = $user->getKey();

        if (! is_scalar($userKey)) {
            return false;
        }

        return $note->assignments->contains(
            static fn (mixed $assignment): bool => $assignment instanceof NoteAssignment
                && $assignment->completed_at === null
                && $assignment->assignee_type === $user->getMorphClass()
                && (string) $assignment->assignee_id === (string) $userKey,
        );
    }

    private function selectedStatus(): ?NoteStatus
    {
        if ($this->statusFilter === 'all') {
            return null;
        }

        return NoteStatus::tryFrom($this->statusFilter);
    }

    private function user(): ?Model
    {
        $user = auth()->user();

        return $user instanceof Model ? $user : null;
    }

    private function modelKeyLabel(Model $model): string
    {
        $key = $model->getKey();

        if (is_scalar($key)) {
            return (string) $key;
        }

        return '';
    }

    /**
     * @param  list<string>  $attributes
     */
    private function labelFromAttributes(Model $model, array $attributes): ?string
    {
        $loadedAttributes = $model->getAttributes();

        foreach ($attributes as $attribute) {
            if (! array_key_exists($attribute, $loadedAttributes)) {
                continue;
            }

            $value = $loadedAttributes[$attribute];

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function noteVisibleToCurrentUser(int $noteId): ?Note
    {
        /** @var Note|null $note */
        $note = $this->inboxNotes()->firstWhere('id', $noteId);

        return $note;
    }

    private function attentionCountsCache(): UserAttentionCountsCache
    {
        return resolve(UserAttentionCountsCache::class);
    }

    private function forgetAttentionCounts(): void
    {
        $this->initialCounts = null;

        $user = $this->user();

        if ($user instanceof Model) {
            $this->attentionCountsCache()->forgetUser($user);
        }
    }
}
