<?php

declare(strict_types=1);

use Capell\Notes\Enums\NoteReminderRecurrence;
use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Enums\NoteVisibility;
use Capell\Notes\Models\Note;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('loads the notes package tables', function (): void {
    expect(Schema::hasTable('notes'))->toBeTrue()
        ->and(Schema::hasTable('note_assignments'))->toBeTrue()
        ->and(Schema::hasTable('note_mentions'))->toBeTrue()
        ->and(Schema::hasTable('note_reminders'))->toBeTrue();
});

it('creates the expected notes schema with guarded migrations', function (): void {
    Schema::dropIfExists('note_reminders');
    Schema::dropIfExists('note_mentions');
    Schema::dropIfExists('note_assignments');
    Schema::dropIfExists('notes');

    $migration = include dirname(__DIR__, 3) . '/database/migrations/2026_05_10_190862_01_create_notes_tables.php';

    $migration->up();
    $migration->up();

    expect(Schema::hasColumns('notes', [
        'id',
        'subject_type',
        'subject_id',
        'author_type',
        'author_id',
        'body',
        'status',
        'visibility',
        'resolved_at',
        'archived_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('note_assignments', [
            'note_id',
            'assignee_type',
            'assignee_id',
            'assigned_by_type',
            'assigned_by_id',
            'completed_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('note_mentions', [
            'note_id',
            'mentioned_type',
            'mentioned_id',
            'mentioned_by_type',
            'mentioned_by_id',
            'read_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('note_reminders', [
            'note_id',
            'due_at',
            'timezone',
            'recurrence',
            'next_due_at',
            'last_notified_at',
            'completed_at',
            'cancelled_at',
        ]))->toBeTrue()
        ->and(Schema::hasIndex('notes', ['subject_type', 'subject_id', 'status']))->toBeTrue()
        ->and(Schema::hasIndex('note_assignments', ['note_id', 'assignee_type', 'assignee_id'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('note_mentions', ['note_id', 'mentioned_type', 'mentioned_id'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('note_reminders', ['note_id'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('note_reminders', ['next_due_at', 'completed_at', 'cancelled_at']))->toBeTrue()
        ->and(Schema::hasIndex('note_reminders', ['due_at', 'completed_at', 'cancelled_at']))->toBeTrue()
        ->and(DB::table('notes')->insertGetId([
            'subject_type' => (new User)->getMorphClass(),
            'subject_id' => User::factory()->create()->getKey(),
            'author_type' => (new User)->getMorphClass(),
            'author_id' => User::factory()->create()->getKey(),
            'body' => 'Schema default note',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toBeInt()
        ->and(DB::table('notes')->value('status'))->toBe(NoteStatus::Open->value)
        ->and(DB::table('notes')->value('visibility'))->toBe(NoteVisibility::RecordEditors->value);

    DB::table('note_reminders')->insert([
        'note_id' => DB::table('notes')->value('id'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('note_reminders')->value('recurrence'))->toBe(NoteReminderRecurrence::None->value);

    expect(fn (): bool => DB::table('note_reminders')->insert([
        'note_id' => DB::table('notes')->value('id'),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $migration->down();

    expect(Schema::hasTable('note_reminders'))->toBeFalse()
        ->and(Schema::hasTable('note_mentions'))->toBeFalse()
        ->and(Schema::hasTable('note_assignments'))->toBeFalse()
        ->and(Schema::hasTable('notes'))->toBeFalse();
});

it('deletes note children when the parent note is deleted', function (): void {
    $user = User::factory()->create();
    $noteId = DB::table('notes')->insertGetId([
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->getKey(),
        'author_type' => $user->getMorphClass(),
        'author_id' => $user->getKey(),
        'body' => 'Delete child records with the note.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('note_assignments')->insert([
        'note_id' => $noteId,
        'assignee_type' => $user->getMorphClass(),
        'assignee_id' => $user->getKey(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('note_mentions')->insert([
        'note_id' => $noteId,
        'mentioned_type' => $user->getMorphClass(),
        'mentioned_id' => $user->getKey(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('note_reminders')->insert([
        'note_id' => $noteId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Note::query()->findOrFail($noteId)->delete();

    expect(DB::table('note_assignments')->where('note_id', $noteId)->count())->toBe(0)
        ->and(DB::table('note_mentions')->where('note_id', $noteId)->count())->toBe(0)
        ->and(DB::table('note_reminders')->where('note_id', $noteId)->count())->toBe(0);
});

it('continues creating dependent tables when the notes table already exists', function (): void {
    Schema::dropIfExists('note_reminders');
    Schema::dropIfExists('note_mentions');
    Schema::dropIfExists('note_assignments');
    Schema::dropIfExists('notes');

    Schema::create('notes', function (Blueprint $table): void {
        $table->id();
        $table->text('body');
        $table->timestamps();
    });

    $migration = include dirname(__DIR__, 3) . '/database/migrations/2026_05_10_190862_01_create_notes_tables.php';

    $migration->up();

    expect(Schema::hasTable('notes'))->toBeTrue()
        ->and(Schema::hasTable('note_assignments'))->toBeTrue()
        ->and(Schema::hasTable('note_mentions'))->toBeTrue()
        ->and(Schema::hasTable('note_reminders'))->toBeTrue();

    $migration->down();
});
