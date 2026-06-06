<?php

declare(strict_types=1);

namespace Capell\Notes\Filament\Extenders\Page;

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\Notes\Actions\CreateNoteAction;
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
                        ->getSearchResultsUsing(fn (string $search): array => $this->searchUsers($search))
                        ->getOptionLabelsUsing(fn (array $values): array => $this->userLabelsForIds($values)),
                    Select::make('mention_ids')
                        ->label(__('capell-notes::note.fields.mentions'))
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => $this->searchUsers($search))
                        ->getOptionLabelsUsing(fn (array $values): array => $this->userLabelsForIds($values)),
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
                        body: (string) $data['body'],
                        visibility: NoteVisibility::from((string) $data['visibility']),
                        assignees: $this->usersForIds($data['assignee_ids'] ?? []),
                        mentions: $this->usersForIds($data['mention_ids'] ?? []),
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
        try {
            resolve(NotesManager::class)->ensureSubject($record);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return Gate::allows('update', $record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function reminderData(array $data): ?NoteReminderData
    {
        if (! isset($data['reminder_due_at']) || $data['reminder_due_at'] === null || $data['reminder_due_at'] === '') {
            return null;
        }

        return new NoteReminderData(
            dueAt: CarbonImmutable::parse((string) $data['reminder_due_at']),
            recurrence: NoteReminderRecurrence::tryFrom((string) ($data['reminder_recurrence'] ?? '')) ?? NoteReminderRecurrence::None,
            timezone: (string) ($data['reminder_timezone'] ?? config('app.timezone', 'UTC')),
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
    private function searchUsers(string $search): array
    {
        $userModel = $this->userModel();

        if ($userModel === null) {
            return [];
        }

        $query = $userModel::query();

        if ($search !== '') {
            $query
                ->where('name', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%');
        }

        return $query
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Model $user): array => [$user->getKey() => $this->userLabel($user)])
            ->all();
    }

    /** @return array<int|string, string> */
    private function userLabelsForIds(array $ids): array
    {
        $userModel = $this->userModel();

        if ($userModel === null || $ids === []) {
            return [];
        }

        return $userModel::query()
            ->whereKey($ids)
            ->get()
            ->mapWithKeys(fn (Model $user): array => [$user->getKey() => $this->userLabel($user)])
            ->all();
    }

    /**
     * @return list<Model>
     */
    private function usersForIds(mixed $ids): array
    {
        $userModel = $this->userModel();

        if ($userModel === null || ! is_array($ids) || $ids === []) {
            return [];
        }

        return array_values($userModel::query()
            ->whereKey($ids)
            ->get()
            ->values()
            ->all());
    }

    /** @return class-string<Model>|null */
    private function userModel(): ?string
    {
        $userModel = config('auth.providers.users.model');

        if (! is_string($userModel) || ! is_a($userModel, Model::class, true)) {
            return null;
        }

        return $userModel;
    }

    private function userLabel(Model $user): string
    {
        foreach (['name', 'email'] as $attribute) {
            $value = $user->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return sprintf('%s #%s', class_basename($user), (string) $user->getKey());
    }
}
