<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Support\NotesManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static list<Model> run(Model $subject, Model $actor, iterable<int|string> $ids = [], ?string $search = null, int $limit = 50)
 */
final class ResolveNoteParticipantsAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  iterable<int|string>  $ids
     * @return list<Model>
     */
    public function handle(
        Model $subject,
        Model $actor,
        iterable $ids = [],
        ?string $search = null,
        int $limit = 50,
    ): array {
        $notes = resolve(NotesManager::class);
        $notes->ensureSubject($subject);
        $notes->ensureParticipant($actor);
        Gate::forUser($actor)->authorize('update', $subject);

        $participantModel = $this->participantModel();
        $ids = $this->modelKeys($ids);

        if ($participantModel === null || ($ids === [] && ($search === null || $search === ''))) {
            return [];
        }

        return array_values($participantModel::query()
            ->when($ids !== [], static fn (Builder $query): Builder => $query->whereKey($ids))
            ->when($search !== null && $search !== '', static function (Builder $query) use ($search): void {
                $query->where(static function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->filter(fn (Model $participant): bool => Gate::forUser($participant)->allows('update', $subject))
            ->values()
            ->all());
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
        } catch (InvalidArgumentException) {
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
