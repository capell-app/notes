<?php

declare(strict_types=1);

namespace Capell\Notes\Console;

use Capell\Core\Models\Page;
use Capell\Notes\Enums\NoteReminderRecurrence;
use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Enums\NoteVisibility;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteAssignment;
use Capell\Notes\Models\NoteMention;
use Capell\Notes\Models\NoteReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class DemoCommand extends Command
{
    protected $signature = 'capell:notes-demo {--force : Replace existing Notes demo records}';

    protected $description = 'Seed demo notes for Capell Notes screenshots.';

    public function handle(): int
    {
        $user = $this->firstUser();
        $author = $this->authorUser($user);
        $page = Page::query()->first();

        if (! $page instanceof Page) {
            $this->components->error('A Capell page is required before seeding Notes demo records.');

            return self::FAILURE;
        }

        if ($this->option('force') === true) {
            Note::query()
                ->where('body', 'like', '[Notes demo]%')
                ->get()
                ->each(static function (Note $note): void {
                    $note->delete();
                });
        }

        $assignedNote = $this->note($page, $author, '[Notes demo] Confirm the revised sitemap copy before launch.');
        $this->assignment($assignedNote, $user, $author);

        $mentionNote = $this->note($page, $author, '[Notes demo] @Test User please review the metadata wording.');
        $this->mention($mentionNote, $user, $author);

        $overdueNote = $this->note($page, $author, '[Notes demo] Follow up on the overdue content handoff.');
        $this->assignment($overdueNote, $user, $author);
        $this->reminder($overdueNote, CarbonImmutable::now()->subDay());

        $resolvedNote = $this->note(
            page: $page,
            author: $author,
            body: '[Notes demo] Resolved editorial question kept for workflow proof.',
            status: NoteStatus::Resolved,
        );
        $this->assignment($resolvedNote, $user, $author, completed: true);

        $this->components->info('Seeded Notes demo records.');

        return self::SUCCESS;
    }

    private function firstUser(): Model
    {
        $userModel = $this->userModel();
        $user = $userModel::query()->first();

        throw_unless($user instanceof Model, RuntimeException::class, 'A user is required before seeding Notes demo records.');

        return $user;
    }

    private function authorUser(Model $user): Model
    {
        $userModel = $this->userModel();
        $author = $userModel::query()
            ->whereKeyNot($user->getKey())
            ->first();

        return $author instanceof Model ? $author : $user;
    }

    /**
     * @return class-string<Model>
     */
    private function userModel(): string
    {
        $userModel = config('auth.providers.users.model');

        throw_unless(is_string($userModel) && is_a($userModel, Model::class, true), RuntimeException::class, 'The configured auth user provider model must be an Eloquent model.');

        return $userModel;
    }

    private function note(Page $page, Model $author, string $body, NoteStatus $status = NoteStatus::Open): Note
    {
        return Note::query()->create([
            'subject_type' => $page->getMorphClass(),
            'subject_id' => $page->getKey(),
            'author_type' => $author->getMorphClass(),
            'author_id' => $author->getKey(),
            'body' => $body,
            'status' => $status,
            'visibility' => NoteVisibility::RecordEditors,
            'resolved_at' => $status === NoteStatus::Resolved ? now() : null,
        ]);
    }

    private function assignment(Note $note, Model $user, Model $author, bool $completed = false): void
    {
        NoteAssignment::query()->create([
            'note_id' => $note->getKey(),
            'assignee_type' => $user->getMorphClass(),
            'assignee_id' => $user->getKey(),
            'assigned_by_type' => $author->getMorphClass(),
            'assigned_by_id' => $author->getKey(),
            'completed_at' => $completed ? now() : null,
        ]);
    }

    private function mention(Note $note, Model $user, Model $author): void
    {
        NoteMention::query()->create([
            'note_id' => $note->getKey(),
            'mentioned_type' => $user->getMorphClass(),
            'mentioned_id' => $user->getKey(),
            'mentioned_by_type' => $author->getMorphClass(),
            'mentioned_by_id' => $author->getKey(),
        ]);
    }

    private function reminder(Note $note, CarbonImmutable $dueAt): void
    {
        NoteReminder::query()->create([
            'note_id' => $note->getKey(),
            'due_at' => $dueAt,
            'timezone' => 'UTC',
            'recurrence' => NoteReminderRecurrence::None,
            'next_due_at' => $dueAt,
        ]);
    }
}
