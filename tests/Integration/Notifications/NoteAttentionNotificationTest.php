<?php

declare(strict_types=1);

use Capell\Notes\Actions\CreateNoteAction;
use Capell\Notes\Actions\SendDueNoteReminderNotificationsAction;
use Capell\Notes\Data\CreateNoteData;
use Capell\Notes\Data\NoteReminderData;
use Capell\Notes\Filament\Pages\NotesInboxPage;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteReminder;
use Capell\Notes\Notifications\NoteAttentionNotification;
use Capell\Tests\Fixtures\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('delivers no real notification side effects for rejected creation and delivers valid creation after commit', function (): void {
    Gate::before(static fn (): bool => true);
    config(['queue.default' => 'sync', 'queue.connections.sync.after_commit' => false]);
    $emitted = [];
    Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$emitted): void {
        $emitted[] = $event->notification;
    });
    $author = User::factory()->create();
    $recipient = User::factory()->create();

    expect(fn (): mixed => CreateNoteAction::run(new CreateNoteData(
        subject: $author,
        author: $author,
        body: 'Rejected reminder with mention',
        mentions: [$recipient],
        reminder: new NoteReminderData(CarbonImmutable::now()),
    )))->toThrow(ValidationException::class)
        ->and(Note::query()->count())->toBe(0)
        ->and($emitted)->toBe([]);

    DB::transaction(function () use ($author, $recipient, &$emitted): void {
        CreateNoteAction::run(new CreateNoteData(
            subject: $author,
            author: $author,
            body: 'Valid reminder with participants',
            assignees: [$recipient],
            mentions: [$recipient],
            reminder: new NoteReminderData(CarbonImmutable::now()),
        ));
        expect($emitted)->toBe([])
            ->and($recipient->notifications()->count())->toBe(0);
    });

    expect($emitted)->toHaveCount(2)
        ->and($recipient->notifications()->count())->toBe(2);
});

it('persists attention notifications consumable and renderable by the real Filament inbox', function (string $type): void {
    Gate::before(static fn (): bool => true);
    config(['queue.default' => 'sync', 'queue.connections.sync.after_commit' => false]);
    $note = Note::factory()->private()->create(['body' => 'Private body must never be copied into a notification']);
    $author = $note->author;
    throw_unless($author instanceof User, LogicException::class);
    test()->actingAs($author);

    $due = CarbonImmutable::parse('2026-01-01 09:00', 'UTC');
    if ($type === 'reminder_unsent') {
        NoteReminder::factory()->for($note)->create(['due_at' => $due, 'next_due_at' => $due]);
        expect(SendDueNoteReminderNotificationsAction::run($due))->toBe(0);
    } else {
        // Exercise Laravel's real queued notification and database channel, without a fake.
        $author->notify(new NoteAttentionNotification($note, $type));
    }

    expect($author->notifications()->count())->toBe(1);
    $consumer = new DatabaseNotifications;
    $visible = $consumer->getNotificationsQuery()->get();
    expect($visible)->toHaveCount(1);
    $stored = $visible->first();
    throw_unless($stored instanceof DatabaseNotification, LogicException::class);
    $rendered = $consumer->getNotification($stored);
    $payload = $stored->getAttribute('data');
    throw_unless(is_array($payload), LogicException::class);
    expect($payload['type'])->toBe($type)
        ->and($payload['note_id'])->toBe($note->getKey())
        ->and($rendered->getTitle())->toBe(__('capell-notes::note.notification_mail.' . $type . '.subject'))
        ->and($rendered->getBody())->toBe(__('capell-notes::note.notification_mail.' . $type . '.line'));
    $action = $rendered->getActions()[0];
    throw_unless($action instanceof Action, LogicException::class);
    $html = $rendered->toHtml();
    expect($html)->toContain(NotesInboxPage::getUrl(panel: 'admin'))
        ->not->toContain($note->body)
        ->and($action->getUrl())->toBe(NotesInboxPage::getUrl(panel: 'admin'));

    if ($type === 'reminder_unsent') {
        SendDueNoteReminderNotificationsAction::run($due->addHour());
        expect($author->notifications()->count())->toBe(1);
    }
})->with(['assigned', 'mentioned', 'reminder', 'reminder_unsent']);
