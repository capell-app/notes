<?php

declare(strict_types=1);

namespace Capell\Notes\Filament\Extenders\Page;

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\Notes\Actions\CreateNoteAction;
use Capell\Notes\Actions\ResolveNoteParticipantsAction;
use Capell\Notes\Data\CreateNoteData;
use Capell\Notes\Data\NoteReminderData;
use Capell\Notes\Enums\NoteReminderRecurrence;
use Capell\Notes\Enums\NoteVisibility;
use Capell\Notes\Support\NotesManager;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class CreateNoteResourceHeaderActionExtender implements ResourceHeaderActionExtender
{
    public function supports(string $pageClass): bool
    {
        return resolve(NotesManager::class)->supportsResourcePage($pageClass);
    }

    /** @return array<int, Action> */
    public function actions(): array
    {
        return [
            Action::make('createNote')
                ->label(__('capell-notes::note.actions.create'))
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('gray')
                ->schema([
                    Textarea::make('body')
                        ->label(__('capell-notes::note.fields.body'))
                        ->rows(5)
                        ->required(),
                    Select::make('visibility')
                        ->label(__('capell-notes::note.fields.visibility'))
                        ->options($this->visibilityOptions())
                        ->default(NoteVisibility::RecordEditors->value)
                        ->required(),
                    Select::make('assignee_ids')
                        ->label(__('capell-notes::note.fields.assignees'))
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search, Model $record): array => $this->searchUsers($record, $search))
                        ->getOptionLabelsUsing(fn (array $values, Model $record): array => $this->userLabelsForIds($record, $values)),
                    Select::make('mention_ids')
                        ->label(__('capell-notes::note.fields.mentions'))
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search, Model $record): array => $this->searchUsers($record, $search))
                        ->getOptionLabelsUsing(fn (array $values, Model $record): array => $this->userLabelsForIds($record, $values)),
                    DateTimePicker::make('reminder_due_at')
                        ->label(__('capell-notes::note.fields.reminder_due_at'))
                        ->seconds(false)
                        ->native(false),
                    Select::make('reminder_recurrence')
                        ->label(__('capell-notes::note.fields.reminder_recurrence'))
                        ->options($this->recurrenceOptions())
                        ->default(NoteReminderRecurrence::None->value),
                    TextInput::make('reminder_timezone')
                        ->label(__('capell-notes::note.fields.reminder_timezone'))
                        ->default((string) config('app.timezone', 'UTC'))
                        ->maxLength(64),
                ])
                ->modalSubmitActionLabel(__('capell-notes::note.actions.create'))
                ->authorize(fn (Model $record): bool => $this->canCreateFor($record))
                ->action(function (Model $record, array $data): void {
                    Gate::authorize('update', $record);

                    $author = auth()->user();

                    throw_unless($author instanceof Model, AuthorizationException::class);

                    CreateNoteAction::run(new CreateNoteData(
                        subject: $record,
                        author: $author,
                        body: $this->stringValue($data['body'] ?? null),
                        visibility: NoteVisibility::from($this->stringValue($data['visibility'] ?? null)),
                        assignees: $this->usersForIds($record, $data['assignee_ids'] ?? []),
                        mentions: $this->usersForIds($record, $data['mention_ids'] ?? []),
                        reminder: $this->reminderData($data),
                    ));

                    Notification::make('capell-notes-note-created')
                        ->title(__('capell-notes::note.notifications.created'))
                        ->success()
                        ->send();
                }),
        ];
    }

    private function canCreateFor(Model $record): bool
    {
        if (! CreateNoteAction::canCreateForSubject($record)) {
            return false;
        }

        return Gate::allows('update', $record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function reminderData(array $data): ?NoteReminderData
    {
        if (! isset($data['reminder_due_at']) || $data['reminder_due_at'] === '') {
            return null;
        }

        return new NoteReminderData(
            dueAt: CarbonImmutable::parse($this->stringValue($data['reminder_due_at'] ?? null)),
            recurrence: NoteReminderRecurrence::tryFrom($this->stringValue($data['reminder_recurrence'] ?? null)) ?? NoteReminderRecurrence::None,
            timezone: $this->stringValue($data['reminder_timezone'] ?? config('app.timezone', 'UTC'), 'UTC'),
        );
    }

    /** @return array<string, string> */
    private function visibilityOptions(): array
    {
        return collect(NoteVisibility::cases())
            ->mapWithKeys(fn (NoteVisibility $visibility): array => [$visibility->value => $visibility->getLabel()])
            ->all();
    }

    /** @return array<string, string> */
    private function recurrenceOptions(): array
    {
        return collect(NoteReminderRecurrence::cases())
            ->mapWithKeys(fn (NoteReminderRecurrence $recurrence): array => [$recurrence->value => $recurrence->getLabel()])
            ->all();
    }

    /** @return array<int|string, string> */
    private function searchUsers(Model $subject, string $search): array
    {
        $actor = auth()->user();

        if (! $actor instanceof Model) {
            return [];
        }

        $options = [];

        collect(ResolveNoteParticipantsAction::run($subject, $actor, search: $search))
            ->each(function (Model $user) use (&$options): void {
                $key = $this->modelKey($user);

                if ($key !== null) {
                    $options[$key] = $this->userLabel($user);
                }
            });

        return $options;
    }

    /**
     * @param  array<array-key, mixed>  $ids
     * @return array<int|string, string>
     */
    private function userLabelsForIds(Model $subject, array $ids): array
    {
        $actor = auth()->user();
        $ids = $this->modelKeys($ids);

        if (! $actor instanceof Model || $ids === []) {
            return [];
        }

        $options = [];

        collect(ResolveNoteParticipantsAction::run($subject, $actor, $ids))
            ->each(function (Model $user) use (&$options): void {
                $key = $this->modelKey($user);

                if ($key !== null) {
                    $options[$key] = $this->userLabel($user);
                }
            });

        return $options;
    }

    /**
     * @return list<Model>
     */
    private function usersForIds(Model $subject, mixed $ids): array
    {
        $actor = auth()->user();

        if (! $actor instanceof Model || ! is_array($ids) || $ids === []) {
            return [];
        }

        $ids = $this->modelKeys($ids);

        if ($ids === []) {
            return [];
        }

        return ResolveNoteParticipantsAction::run($subject, $actor, $ids);
    }

    private function userLabel(Model $user): string
    {
        foreach (['name', 'email'] as $attribute) {
            $value = $user->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return sprintf('%s #%s', class_basename($user), $this->stringValue($user->getKey()));
    }

    private function stringValue(mixed $value, string $fallback = ''): string
    {
        return is_string($value) || is_int($value) || is_float($value)
            ? (string) $value
            : $fallback;
    }

    private function modelKey(Model $model): int|string|null
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }

    /**
     * @param  array<array-key, mixed>  $ids
     * @return list<int|string>
     */
    private function modelKeys(array $ids): array
    {
        $keys = [];

        foreach ($ids as $id) {
            if (is_int($id) || is_string($id)) {
                $keys[] = $id;
            }
        }

        return $keys;
    }
}
