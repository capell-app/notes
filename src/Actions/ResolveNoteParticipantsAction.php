<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Support\NotesManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static list<Model> run(Model $subject, iterable<int|string> $ids)
 */
final class ResolveNoteParticipantsAction
{
    use AsObject;

    /**
     * @param  iterable<int|string>  $ids
     * @return list<Model>
     */
    public function handle(Model $subject, iterable $ids): array
    {
        $participantModel = $this->participantModel();
        $ids = $this->modelKeys($ids);

        if ($participantModel === null || $ids === []) {
            return [];
        }

        return $participantModel::query()
            ->whereKey($ids)
            ->get()
            ->filter(fn (Model $participant): bool => Gate::forUser($participant)->allows('update', $subject))
            ->values()
            ->all();
    }

    /**
     * @return class-string<Model>|null
     */
    private function participantModel(): ?string
    {
        $participantModel = config('auth.providers.users.model');

        if (! is_string($participantModel) || ! is_a($participantModel, Model::class, true)) {
            return null;
        }

        try {
            resolve(NotesManager::class)->ensureParticipant(new $participantModel);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $participantModel;
    }

    /**
     * @param  iterable<int|string>  $ids
     * @return list<int|string>
     */
    private function modelKeys(iterable $ids): array
    {
        $keys = [];

        foreach ($ids as $id) {
            if (is_int($id) || is_string($id)) {
                $keys[] = $id;
            }
        }

        return array_values(array_unique($keys, SORT_REGULAR));
    }
}
