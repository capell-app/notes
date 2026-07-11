<?php

declare(strict_types=1);

namespace Capell\Notes\Notifications;

use Capell\Notes\Models\Note;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class NoteAttentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private readonly int|string $noteId;

    public function __construct(
        Note $note,
        private readonly string $type,
    ) {
        $noteId = $note->getKey();

        throw_unless(is_int($noteId) || is_string($noteId), InvalidArgumentException::class);

        $this->noteId = $noteId;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = config('capell-notes.notifications.channels', ['database']);

        if (! is_array($channels)) {
            return Schema::hasTable('notifications') ? ['database'] : [];
        }

        return array_values(array_filter(
            $channels,
            static fn (mixed $channel): bool => is_string($channel)
                && $channel !== ''
                && ($channel !== 'database' || Schema::hasTable('notifications')),
        ));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject((string) __('capell-notes::note.notification_mail.' . $this->type . '.subject'))
            ->line((string) __('capell-notes::note.notification_mail.' . $this->type . '.line'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'note_id' => $this->noteId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
