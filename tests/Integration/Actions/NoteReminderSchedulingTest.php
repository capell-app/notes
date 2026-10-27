<?php

declare(strict_types=1);

use Capell\Notes\Actions\AssignNoteUsersAction;
use Capell\Notes\Actions\CalculateNextNoteReminderDueAction;
use Capell\Notes\Actions\CreateNoteAction;
use Capell\Notes\Actions\SendDueNoteReminderNotificationsAction;
use Capell\Notes\Actions\UpsertNoteReminderAction;
use Capell\Notes\Data\CreateNoteData;
use Capell\Notes\Data\NoteReminderData;
use Capell\Notes\Enums\NoteReminderRecurrence;
use Capell\Notes\Enums\NoteVisibility;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteReminder;
use Capell\Notes\Notifications\NoteAttentionNotification;
use Capell\Tests\Fixtures\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('blocks timezone-aware reminder writes until the deployment is explicitly activated', function (?bool $enabled): void {
    Gate::before(static fn (): bool => true);
    config(['capell-notes.reminders.timezone_schedules_enabled' => $enabled]);
    Notification::fake();
    $author = User::factory()->create();
    $recipient = User::factory()->create();
    $due = CarbonImmutable::parse('2026-03-07 09:00', 'America/New_York');

    expect(fn (): mixed => CreateNoteAction::run(new CreateNoteData(
        subject: $author,
        author: $author,
        body: 'No mixed scheduler versions',
        assignees: [$recipient],
        mentions: [$recipient],
        reminder: new NoteReminderData($due, NoteReminderRecurrence::Daily, 'America/New_York'),
    )))->toThrow(ValidationException::class)
        ->and(Note::query()->count())->toBe(0);
    Notification::assertNothingSent();

    $note = Note::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    $legacy = NoteReminder::factory()->for($note)->create(['due_at' => $due, 'next_due_at' => $due]);
    $original = $legacy->refresh()->getRawOriginal();
    expect(fn () => UpsertNoteReminderAction::run($note, new NoteReminderData($due, timezone: 'America/New_York')))
        ->toThrow(ValidationException::class)
        ->and($legacy->refresh()->getRawOriginal())->toBe($original);
})->with(['missing activation' => null, 'disabled activation' => false]);

it('keeps the New York cadence after activation when a scheduler runs late before spring DST', function (): void {
    Gate::before(static fn (): bool => true);
    config(['capell-notes.reminders.timezone_schedules_enabled' => true]);
    Notification::fake();
    $note = Note::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    $reminder = UpsertNoteReminderAction::run($note, new NoteReminderData(
        CarbonImmutable::parse('2026-03-07 09:00', 'America/New_York'),
        NoteReminderRecurrence::Daily,
        'America/New_York',
    ));
    throw_unless($reminder instanceof NoteReminder, LogicException::class);
    expect(SendDueNoteReminderNotificationsAction::run(CarbonImmutable::parse('2026-03-07 14:30', 'UTC')))->toBe(1)
        ->and($reminder->refresh()->schedule_version)->toBe(NoteReminder::UTC_SCHEDULE_VERSION)
        ->and($reminder->next_due_at?->setTimezone('America/New_York')->format('Y-m-d H:i'))->toBe('2026-03-08 09:00');
});

it('keeps recurring local wall time across DST and delayed execution', function (string $due, string $processed, string $next): void {
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
    Notification::fake();
    $note = Note::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    $reminder = UpsertNoteReminderAction::run($note, new NoteReminderData(
        CarbonImmutable::parse($due, 'Europe/London'),
        NoteReminderRecurrence::Daily,
        'Europe/London',
    ));
    throw_unless($reminder instanceof NoteReminder, LogicException::class);
    expect(SendDueNoteReminderNotificationsAction::run(CarbonImmutable::parse($processed, 'UTC')))->toBe(1)
        ->and($reminder->refresh()->next_due_at?->utc()->format('Y-m-d H:i'))->toBe($next);
})->with([
    'spring' => ['2026-03-28 09:00', '2026-03-28 11:30', '2026-03-29 08:00'],
    'autumn' => ['2026-10-24 09:00', '2026-10-24 11:30', '2026-10-25 09:00'],
    'late by days' => ['2026-03-28 09:00', '2026-03-31 11:30', '2026-04-01 08:00'],
]);

it('rejects an invalid reminder timezone before persisting', function (): void {
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
    $note = Note::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    expect(fn () => UpsertNoteReminderAction::run($note, new NoteReminderData(CarbonImmutable::now(), timezone: 'Invalid/Zone')))
        ->toThrow(ValidationException::class)
        ->and($note->reminder()->exists())->toBeFalse();
});

it('requires an explicit eligible assignee for a private personal reminder and rolls back creation', function (): void {
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
    $author = User::factory()->create();
    expect(fn (): mixed => CreateNoteAction::run(new CreateNoteData(
        subject: $author,
        author: $author,
        body: 'Personal follow-up',
        visibility: NoteVisibility::Private,
        reminder: new NoteReminderData(CarbonImmutable::now()),
    )))->toThrow(ValidationException::class)
        ->and(Note::query()->count())->toBe(0);
});

it('retains an unsent occurrence without claiming notification when every assignment is ineligible', function (string $reason): void {
    $access = (object) ['revoked' => false, 'author' => null];
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update'
        ? (! $access->revoked || $user->is($access->author)) : null);
    Notification::fake();
    $note = Note::factory()->private()->create();
    $assignee = User::factory()->create();
    if ($reason !== 'unassigned') {
        AssignNoteUsersAction::run($note, [$assignee], assignedBy: $note->author);
    }

    if ($reason === 'completed') {
        $note->assignments()->update(['completed_at' => now()]);
    }

    if ($reason === 'revoked') {
        $access->author = $note->author;
        $access->revoked = true;
    }

    Notification::fake();
    // Legacy records can already have no eligible recipient.
    $due = CarbonImmutable::parse('2026-01-01 09:00', 'UTC');
    $reminder = NoteReminder::factory()->for($note)->create(['due_at' => $due, 'next_due_at' => $due]);
    expect(SendDueNoteReminderNotificationsAction::run($due->addHour()))->toBe(0)
        ->and($reminder->refresh()->last_notified_at)->toBeNull()
        ->and($reminder->next_due_at->equalTo($due))->toBeTrue()
        ->and($reminder->delivery_failed_at)->not->toBeNull();
    Notification::assertNotSentTo($assignee, NoteAttentionNotification::class);
    Notification::assertSentTo($note->author, NoteAttentionNotification::class, fn (NoteAttentionNotification $notification): bool => $notification->toArray($note->author)['type'] === 'reminder_unsent');
    SendDueNoteReminderNotificationsAction::run($due->addHours(2));
    Notification::assertSentToTimes($note->author, NoteAttentionNotification::class, 1);
})->with(['unassigned', 'completed', 'revoked']);

it('notifies each active eligible assignee once and does not repeat a one-off', function (): void {
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
    Notification::fake();
    $note = Note::factory()->private()->create();
    $second = User::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author, $second], assignedBy: $note->author);
    Notification::fake();
    $due = CarbonImmutable::parse('2026-01-01 09:00', 'UTC');
    $reminder = UpsertNoteReminderAction::run($note, new NoteReminderData($due));
    throw_unless($reminder instanceof NoteReminder, LogicException::class);
    expect(SendDueNoteReminderNotificationsAction::run($due))->toBe(2)
        ->and(SendDueNoteReminderNotificationsAction::run($due->addDay()))->toBe(0)
        ->and($reminder->refresh()->last_notified_at)->not->toBeNull();
    Notification::assertSentToTimes($note->author, NoteAttentionNotification::class, 1);
    Notification::assertSentToTimes($second, NoteAttentionNotification::class, 1);
});

it('preserves legacy instants and the already scheduled application-clock cadence despite descriptive timezone metadata', function (): void {
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
    $note = Note::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    Notification::fake();
    $reminder = NoteReminder::factory()->for($note)->create([
        'due_at' => '2026-01-01 09:00:00', 'next_due_at' => '2026-01-03 11:00:00',
        'timezone' => 'Legacy/Descriptive', 'recurrence' => NoteReminderRecurrence::Daily,
    ]);
    expect(SendDueNoteReminderNotificationsAction::run(CarbonImmutable::parse('2026-01-03 13:00', 'UTC')))->toBe(1)
        ->and($reminder->refresh()->due_at->format('Y-m-d H:i'))->toBe('2026-01-01 09:00')
        ->and($reminder->next_due_at?->utc()->format('Y-m-d H:i'))->toBe('2026-01-04 11:00');
});

it('recovers an unsent reminder when an eligible assignee is restored', function (): void {
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
    $note = Note::factory()->private()->create();
    Notification::fake();
    $due = CarbonImmutable::parse('2026-01-01 09:00', 'UTC');
    $reminder = NoteReminder::factory()->for($note)->create(['due_at' => $due, 'next_due_at' => $due]);
    expect(SendDueNoteReminderNotificationsAction::run($due))->toBe(0);
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    Notification::fake();
    expect(SendDueNoteReminderNotificationsAction::run($due->addHour()))->toBe(1)
        ->and($reminder->refresh()->delivery_failed_at)->toBeNull()
        ->and($reminder->completed_at)->not->toBeNull();
});

it('does not notify a revoked author or any cross-site assignee', function (): void {
    $access = (object) ['allowed' => true];
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? $access->allowed : null);
    $note = Note::factory()->private()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    $due = CarbonImmutable::parse('2026-01-01 09:00', 'UTC');
    $reminder = UpsertNoteReminderAction::run($note, new NoteReminderData($due));
    throw_unless($reminder instanceof NoteReminder, LogicException::class);
    $access->allowed = false;
    Notification::fake();
    expect(SendDueNoteReminderNotificationsAction::run($due))->toBe(0)
        ->and($reminder->refresh()->last_notified_at)->toBeNull();
    Notification::assertNothingSent();
});

it('does not let failed reminders starve later due work under the record limit', function (): void {
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
    $due = CarbonImmutable::parse('2026-01-01 09:00', 'UTC');
    NoteReminder::factory()->create(['due_at' => $due, 'next_due_at' => $due]);
    $note = Note::factory()->create();
    AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
    UpsertNoteReminderAction::run($note, new NoteReminderData($due->addMinute()));
    Notification::fake();
    expect(SendDueNoteReminderNotificationsAction::run($due->addHour(), 1))->toBe(0)
        ->and(SendDueNoteReminderNotificationsAction::run($due->addHours(2), 1))->toBe(1);
});

it('stores the same instant when the application timezone differs from the reminder zone', function (): void {
    config(['app.timezone' => 'Asia/Tokyo']);
    date_default_timezone_set('Asia/Tokyo');
    try {
        Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
        $note = Note::factory()->create();
        AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
        $reminder = UpsertNoteReminderAction::run($note, new NoteReminderData(
            CarbonImmutable::parse('2026-07-01 09:00', 'America/New_York'),
            timezone: 'America/New_York',
        ));
        throw_unless($reminder instanceof NoteReminder, LogicException::class);
        expect($reminder->refresh()->due_at?->utc()->format('Y-m-d H:i'))->toBe('2026-07-01 13:00');
        Notification::fake();
        expect(SendDueNoteReminderNotificationsAction::run(CarbonImmutable::parse('2026-07-01 12:59', 'UTC')))->toBe(0)
            ->and(SendDueNoteReminderNotificationsAction::run(CarbonImmutable::parse('2026-07-01 13:00', 'UTC')))->toBe(1);
    } finally {
        date_default_timezone_set('UTC');
    }
});

it('retains its original calendar anchor through gaps short months and leap years', function (string $due, string $processed, NoteReminderRecurrence $recurrence, string $next): void {
    $reminder = new NoteReminder([
        'due_at' => CarbonImmutable::parse($due, 'Europe/London')->utc(),
        'next_due_at' => CarbonImmutable::parse($due, 'Europe/London')->utc(),
        'recurrence' => $recurrence, 'timezone' => 'Europe/London', 'schedule_version' => 2,
    ]);
    $result = CalculateNextNoteReminderDueAction::run($reminder, CarbonImmutable::parse($processed, 'UTC'));
    expect($result?->utc()->format('Y-m-d H:i'))->toBe($next);
})->with([
    'spring gap' => ['2026-03-28 01:30', '2026-03-28 03:00', NoteReminderRecurrence::Daily, '2026-03-29 01:30'],
    'after spring gap' => ['2026-03-28 01:30', '2026-03-29 03:00', NoteReminderRecurrence::Daily, '2026-03-30 00:30'],
    'autumn overlap' => ['2026-10-24 01:30', '2026-10-24 03:00', NoteReminderRecurrence::Daily, '2026-10-25 00:30'],
    'after autumn overlap' => ['2026-10-24 01:30', '2026-10-25 03:00', NoteReminderRecurrence::Daily, '2026-10-26 01:30'],
    'weekly' => ['2026-03-28 09:00', '2026-03-28 11:00', NoteReminderRecurrence::Weekly, '2026-04-04 08:00'],
    'short month' => ['2026-01-31 09:00', '2026-01-31 11:00', NoteReminderRecurrence::Monthly, '2026-02-28 09:00'],
    'restore day after short month' => ['2026-01-31 09:00', '2026-02-28 11:00', NoteReminderRecurrence::Monthly, '2026-03-31 08:00'],
    'leap year' => ['2024-02-29 09:00', '2024-02-29 11:00', NoteReminderRecurrence::Yearly, '2025-02-28 09:00'],
]);

it('dispatches the intended number of due reminder records through the scheduler command', function (?string $limit, int $expected): void {
    Gate::before(static fn (User $user, string $ability): ?bool => $ability === 'update' ? true : null);
    $this->freezeTime();
    foreach (range(1, 2) as $index) {
        $note = Note::factory()->create();
        AssignNoteUsersAction::run($note, [$note->author], assignedBy: $note->author);
        UpsertNoteReminderAction::run($note, new NoteReminderData(CarbonImmutable::now()->subMinute()));
    }

    Notification::fake();
    $this->artisan('capell:notes:send-due-reminders', $limit === null ? [] : ['--limit' => $limit])->assertSuccessful();
    expect(NoteReminder::query()->whereNotNull('completed_at')->count())->toBe($expected);
    Notification::assertCount($expected);
})->with(['default' => [null, 2], 'one record' => ['1', 1], 'invalid falls back' => ['invalid', 2], 'zero falls back' => ['0', 2]]);
