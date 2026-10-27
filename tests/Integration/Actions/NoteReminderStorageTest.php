<?php

declare(strict_types=1);

use Capell\Notes\Actions\AssignNoteUsersAction;
use Capell\Notes\Actions\BuildUserAttentionCountsAction;
use Capell\Notes\Actions\BuildUserInboxNotesAction;
use Capell\Notes\Actions\SendDueNoteReminderNotificationsAction;
use Capell\Notes\Actions\UpsertNoteReminderAction;
use Capell\Notes\Data\NoteReminderData;
use Capell\Notes\Enums\NoteReminderRecurrence;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteReminder;
use Capell\Notes\Notifications\NoteAttentionNotification;
use Capell\Tests\Fixtures\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
    Notification::fake();
});

afterEach(function (): void {
    date_default_timezone_set('UTC');
    CarbonImmutable::setTestNow();
});

it('reloads and dispatches each application-zone overlap occurrence at its intended instant', function (string $zone, string $instant): void {
    config(['app.timezone' => $zone]);
    date_default_timezone_set($zone);
    $note = Note::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    Notification::fake();
    $due = CarbonImmutable::parse($instant, 'UTC');
    $reminder = UpsertNoteReminderAction::run($note, new NoteReminderData($due));
    throw_unless($reminder instanceof NoteReminder, LogicException::class);

    expect($reminder->refresh()->due_at?->equalTo($due))->toBeTrue()
        ->and($reminder->next_due_at?->equalTo($due))->toBeTrue()
        ->and(SendDueNoteReminderNotificationsAction::run($due->subHour()))->toBe(0)
        ->and(SendDueNoteReminderNotificationsAction::run($due->subMinute()))->toBe(0)
        ->and(SendDueNoteReminderNotificationsAction::run($due))->toBe(1)
        ->and($reminder->refresh()->completed_at?->equalTo($due))->toBeTrue()
        ->and($reminder->last_notified_at?->equalTo($due))->toBeTrue()
        ->and(SendDueNoteReminderNotificationsAction::run($due->addHour()))->toBe(0);
    Notification::assertSentToTimes($note->author, NoteAttentionNotification::class, 1);
})->with([
    'New York first occurrence' => ['America/New_York', '2026-11-01 05:30'],
    'New York second occurrence' => ['America/New_York', '2026-11-01 06:30'],
    'London first occurrence' => ['Europe/London', '2026-10-25 00:30'],
    'London second occurrence' => ['Europe/London', '2026-10-25 01:30'],
]);

it('round-trips and dispatches recurring occurrences through an application-zone overlap', function (string $zone, string $first): void {
    config(['app.timezone' => $zone]);
    date_default_timezone_set($zone);
    $note = Note::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    Notification::fake();
    $due = CarbonImmutable::parse($first, 'UTC');
    $reminder = UpsertNoteReminderAction::run($note, new NoteReminderData($due, NoteReminderRecurrence::Daily));
    throw_unless($reminder instanceof NoteReminder, LogicException::class);

    expect(SendDueNoteReminderNotificationsAction::run($due))->toBe(1)
        ->and($reminder->refresh()->next_due_at?->equalTo($due->addDay()))->toBeTrue()
        ->and(SendDueNoteReminderNotificationsAction::run($due->addDay()->subMinute()))->toBe(0)
        ->and(SendDueNoteReminderNotificationsAction::run($due->addDay()))->toBe(1)
        ->and($reminder->refresh()->last_notified_at?->equalTo($due->addDay()))->toBeTrue()
        ->and($reminder->next_due_at?->equalTo($due->addDays(2)))->toBeTrue();
    Notification::assertSentToTimes($note->author, NoteAttentionNotification::class, 2);
})->with([
    'New York first occurrence' => ['America/New_York', '2026-10-31 05:30'],
    'New York second occurrence' => ['America/New_York', '2026-10-31 06:30'],
    'London first occurrence' => ['Europe/London', '2026-10-24 00:30'],
    'London second occurrence' => ['Europe/London', '2026-10-24 01:30'],
]);

it('uses application-day boundaries for attention counts and inbox filters across storage versions', function (int $version, bool $fallback): void {
    config(['app.timezone' => 'America/New_York']);
    date_default_timezone_set('America/New_York');
    $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00', 'America/New_York'));
    $user = Note::factory()->create()->author;
    $notes = [];
    foreach (['2026-11-01 03:30', '2026-11-01 05:30', '2026-11-01 06:30', '2026-11-02 04:30', '2026-11-02 05:30'] as $instant) {
        $note = Note::factory()->create();
        AssignNoteUsersAction::run($note, [$user], assignedBy: $note->author);
        $due = CarbonImmutable::parse($instant, 'UTC');
        if ($version === 3) {
            $reminder = UpsertNoteReminderAction::run($note, new NoteReminderData($due));
        } else {
            $reminder = NoteReminder::factory()->for($note)->create([
                'schedule_version' => $version,
                'due_at' => $due->setTimezone('America/New_York'),
                'next_due_at' => $due->setTimezone('America/New_York'),
            ]);
        }

        throw_unless($reminder instanceof NoteReminder, LogicException::class);
        if ($fallback) {
            $reminder->update(['next_due_at' => null]);
        }

        $notes[] = $note;
    }

    $counts = BuildUserAttentionCountsAction::run($user);
    expect($counts->dueToday)->toBe(3)->and($counts->overdue)->toBe(1)
        ->and(BuildUserInboxNotesAction::run($user, attention: 'dueToday')->modelKeys())->toEqualCanonicalizing([$notes[1]->id, $notes[2]->id, $notes[3]->id])
        ->and(BuildUserInboxNotesAction::run($user, attention: 'overdue')->modelKeys())->toBe([$notes[0]->id]);
})->with([1, 2, 3])->with([false, true]);

it('preserves application-zone interpretation for stored legacy reminders', function (int $version): void {
    config(['app.timezone' => 'Asia/Tokyo']);
    date_default_timezone_set('Asia/Tokyo');
    $note = Note::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    $reminder = NoteReminder::factory()->for($note)->create([
        'schedule_version' => $version, 'timezone' => 'UTC',
        'due_at' => '2026-07-01 22:00:00', 'next_due_at' => '2026-07-01 22:00:00',
    ]);
    $due = CarbonImmutable::parse('2026-07-01 13:00', 'UTC');
    expect($reminder->refresh()->due_at?->equalTo($due))->toBeTrue()
        ->and(SendDueNoteReminderNotificationsAction::run($due->subHour()))->toBe(0)
        ->and(SendDueNoteReminderNotificationsAction::run($due->subMinute()))->toBe(0)
        ->and(SendDueNoteReminderNotificationsAction::run($due))->toBe(1)
        ->and($reminder->refresh()->getRawOriginal('last_notified_at'))->toBe('2026-07-01 22:00:00');
})->with([1, 2]);

it('does not send the second New York overlap occurrence during the first occurrence', function (): void {
    config(['app.timezone' => 'America/New_York']);
    date_default_timezone_set('America/New_York');
    $note = Note::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    $reminder = UpsertNoteReminderAction::run($note, new NoteReminderData(CarbonImmutable::parse('2026-11-01 06:30', 'UTC')));
    throw_unless($reminder instanceof NoteReminder, LogicException::class);
    $reminder->refresh();
    expect(SendDueNoteReminderNotificationsAction::run(CarbonImmutable::parse('2026-11-01 05:30', 'UTC')))->toBe(0)
        ->and($reminder->refresh()->completed_at)->toBeNull()
        ->and(SendDueNoteReminderNotificationsAction::run(CarbonImmutable::parse('2026-11-01 06:30', 'UTC')))->toBe(1);
});

it('replaces a legacy schedule with UTC instants and clears prior delivery state', function (int $version): void {
    config(['app.timezone' => 'America/New_York']);
    date_default_timezone_set('America/New_York');
    $note = Note::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    $legacy = NoteReminder::factory()->for($note)->create([
        'schedule_version' => $version,
        'due_at' => '2026-10-31 01:30:00', 'next_due_at' => null,
        'last_notified_at' => '2026-10-31 01:30:00', 'completed_at' => '2026-10-31 01:30:00',
        'cancelled_at' => '2026-10-31 01:30:00', 'delivery_failed_at' => '2026-10-31 01:30:00',
    ]);
    $due = CarbonImmutable::parse('2026-11-01 06:30', 'UTC');
    $replacement = UpsertNoteReminderAction::run($note, new NoteReminderData($due));
    throw_unless($replacement instanceof NoteReminder, LogicException::class);
    expect($replacement->id)->toBe($legacy->id)
        ->and($replacement->refresh()->schedule_version)->toBe(NoteReminder::UTC_SCHEDULE_VERSION)
        ->and($replacement->getRawOriginal('due_at'))->toBe('2026-11-01 06:30:00')
        ->and($replacement->due_at?->equalTo($due))->toBeTrue()
        ->and($replacement->next_due_at?->equalTo($due))->toBeTrue()
        ->and($replacement->last_notified_at)->toBeNull()->and($replacement->completed_at)->toBeNull()
        ->and($replacement->cancelled_at)->toBeNull()->and($replacement->delivery_failed_at)->toBeNull()
        ->and(SendDueNoteReminderNotificationsAction::run($due->subHour()))->toBe(0)
        ->and(SendDueNoteReminderNotificationsAction::run($due))->toBe(1);
})->with([1, 2]);

it('round-trips UTC recovery stamps and recovers an unsent overlap occurrence', function (): void {
    config(['app.timezone' => 'America/New_York']);
    date_default_timezone_set('America/New_York');
    $note = Note::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    $due = CarbonImmutable::parse('2026-11-01 06:30', 'UTC');
    $reminder = UpsertNoteReminderAction::run($note, new NoteReminderData($due));
    throw_unless($reminder instanceof NoteReminder, LogicException::class);
    $note->assignments()->update(['completed_at' => now()]);
    expect(SendDueNoteReminderNotificationsAction::run($due))->toBe(0)
        ->and($reminder->refresh()->delivery_failed_at?->equalTo($due))->toBeTrue()
        ->and($reminder->next_due_at?->equalTo($due))->toBeTrue()
        ->and($reminder->last_notified_at)->toBeNull();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    expect(SendDueNoteReminderNotificationsAction::run($due->addHour()))->toBe(1)
        ->and($reminder->refresh()->delivery_failed_at)->toBeNull()
        ->and($reminder->last_notified_at?->equalTo($due->addHour()))->toBeTrue();
});
