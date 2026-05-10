<?php

declare(strict_types=1);

namespace Capell\Notes\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class NotesManager
{
    /** @var array<class-string<Model>, true> */
    private array $subjectClasses = [];

    /** @var array<class-string<Model>, true> */
    private array $participantClasses = [];

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function registerSubject(string $modelClass): void
    {
        $this->subjectClasses[$modelClass] = true;
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
        if ($this->isRegistered($subject, $this->subjectClasses)) {
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
}
