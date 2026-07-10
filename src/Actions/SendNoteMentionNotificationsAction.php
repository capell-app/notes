<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Capell\Notes\Notifications\NoteAttentionNotification;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(Note $note, list<Model> $mentions, ?Model $mentionedBy = null)
 */
final class SendNoteMentionNotificationsAction
{
    use AsObject;

    /**
     * @param  list<Model>  $mentions
     */
    public function handle(Note $note, array $mentions, ?Model $mentionedBy = null): void
    {
        foreach ($mentions as $mentioned) {
            if ($this->isSameUser($mentioned, $mentionedBy)) {
                continue;
            }

            if (! method_exists($mentioned, 'notify')) {
                continue;
            }

            $mentioned->notify(new NoteAttentionNotification((int) $note->getKey(), 'mentioned'));
        }
    }

    private function isSameUser(Model $user, ?Model $actor): bool
    {
        return $actor instanceof Model
            && $user->getMorphClass() === $actor->getMorphClass()
            && $this->stringValue($user->getKey()) === $this->stringValue($actor->getKey());
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value)
            ? (string) $value
            : '';
    }
}
