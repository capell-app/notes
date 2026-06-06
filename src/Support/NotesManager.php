<?php

declare(strict_types=1);

namespace Capell\Notes\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class NotesManager
{
    /** @var array<class-string<Model>, list<class-string>> */
    private array $subjectClasses = [];

    /** @var array<class-string<Model>, true> */
    private array $participantClasses = [];

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<class-string>  $resourcePageClasses
     */
    public function registerSubject(string $modelClass, array $resourcePageClasses = []): void
    {
        $this->subjectClasses[$modelClass] = array_values(array_unique([
            ...($this->subjectClasses[$modelClass] ?? []),
            ...$resourcePageClasses,
        ]));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function registerParticipant(string $modelClass): void
    {
        $this->participantClasses[$modelClass] = true;
    }

    public function ensureSubject(Model $subject): void
    {
        if ($this->isSubjectRegistered($subject)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Notes cannot be attached to [%s] because it has not been registered as a note subject.',
            $subject::class,
        ));
    }

    public function ensureParticipant(Model $participant): void
    {
        if ($this->isRegistered($participant, $this->participantClasses)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Notes cannot assign or mention [%s] because it has not been registered as a note participant.',
            $participant::class,
        ));
    }

    public function clear(): void
    {
        $this->subjectClasses = [];
        $this->participantClasses = [];
    }

    public function supportsResourcePage(string $pageClass): bool
    {
        foreach ($this->subjectClasses as $resourcePageClasses) {
            if (in_array($pageClass, $resourcePageClasses, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<class-string<Model>, true>  $registeredClasses
     */
    private function isRegistered(Model $model, array $registeredClasses): bool
    {
        foreach (array_keys($registeredClasses) as $registeredClass) {
            if ($model instanceof $registeredClass) {
                return true;
            }
        }

        return false;
    }

    private function isSubjectRegistered(Model $model): bool
    {
        foreach (array_keys($this->subjectClasses) as $registeredClass) {
            if ($model instanceof $registeredClass) {
                return true;
            }
        }

        return false;
    }
}
