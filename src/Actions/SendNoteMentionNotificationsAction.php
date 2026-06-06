<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Capell\Notes\Notifications\NoteAttentionNotification;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(Note $note, array $mentions, ?Model $mentionedBy = null)
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
            if ($this->isSameUser($mentioned, $mentionedBy) || ! method_exists($mentioned, 'notify')) {
                continue;
            }

            $mentioned->notify(new NoteAttentionNotification($note, 'mentioned'));
        }
    }

    private function isSameUser(Model $user, ?Model $actor): bool
    {
        return $actor instanceof Model
            && $user->getMorphClass() === $actor->getMorphClass()
            && (string) $user->getKey() === (string) $actor->getKey();
    }
}
