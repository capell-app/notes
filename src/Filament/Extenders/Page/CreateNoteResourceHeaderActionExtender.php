<?php

declare(strict_types=1);

namespace Capell\Notes\Filament\Extenders\Page;

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\Admin\Filament\Resources\Pages\Pages\EditPage;
use Capell\Core\Models\Page;
use Capell\Notes\Actions\CreateNoteAction;
use Capell\Notes\Data\CreateNoteData;
use Capell\Notes\Enums\NoteVisibility;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class CreateNoteResourceHeaderActionExtender implements ResourceHeaderActionExtender
{
    public function supports(string $pageClass): bool
    {
        return $pageClass === EditPage::class;
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
                        ->options(fn (): array => $this->userOptions())
                        ->multiple()
                        ->searchable(),
                    Select::make('mention_ids')
                        ->label(__('capell-notes::note.fields.mentions'))
                        ->options(fn (): array => $this->userOptions())
                        ->multiple()
                        ->searchable(),
                ])
                ->modalSubmitActionLabel(__('capell-notes::note.actions.create'))
                ->authorize(fn (Page $record): bool => Gate::allows('update', $record))
                ->action(function (Page $record, array $data): void {
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
                    ));

                    Notification::make('capell-notes-note-created')
                        ->title(__('capell-notes::note.notifications.created'))
                        ->success()
                        ->send();
                }),
        ];
    }

    /** @return array<string, string> */
    private function visibilityOptions(): array
    {
        return collect(NoteVisibility::cases())
            ->mapWithKeys(fn (NoteVisibility $visibility): array => [$visibility->value => $visibility->getLabel()])
            ->all();
    }

    /** @return array<int|string, string> */
    private function userOptions(): array
    {
        $userModel = $this->userModel();

        if ($userModel === null) {
            return [];
        }

        return $userModel::query()
            ->limit(100)
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
