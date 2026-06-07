<?php

declare(strict_types=1);

namespace Capell\Notes\Notifications;

use Capell\Notes\Models\Note;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NoteAttentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Note $note,
        private readonly string $type,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = config('capell-notes.notifications.channels', ['database']);

        if (! is_array($channels)) {
            return ['database'];
        }

        return array_values(array_filter(
            $channels,
            static fn (mixed $channel): bool => is_string($channel) && $channel !== '',
        ));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject((string) __('capell-notes::note.notification_mail.' . $this->type . '.subject'))
            ->line((string) __('capell-notes::note.notification_mail.' . $this->type . '.line'))
            ->line($this->excerpt());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'note_id' => $this->note->getKey(),
            'subject_type' => $this->note->subject_type,
            'subject_id' => $this->note->subject_id,
            'excerpt' => $this->excerpt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    private function excerpt(): string
    {
        return str($this->note->body)
            ->squish()
            ->limit(160)
            ->toString();
    }
}
