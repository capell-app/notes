<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Capell\Notes\Enums\NoteReminderRecurrence;
use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Enums\NoteVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! TableName::existsAndIsOwned('notes', basename(__FILE__, '.php'))) {
            Schema::create(TableName::resolve('notes'), function (Blueprint $table): void {
                $table->id();
                $table->morphs('subject');
                $table->morphs('author');
                $table->text('body');
                $table->string('status')->default(NoteStatus::Open->value)->index();
                $table->string('visibility')->default(NoteVisibility::RecordEditors->value)->index();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->index(['subject_type', 'subject_id', 'status'], TableName::namedIndex('notes', 'notes_subject_status_index'));
            });
        }

        if (! TableName::existsAndIsOwned('note_assignments', basename(__FILE__, '.php'))) {
            Schema::create(TableName::resolve('note_assignments'), function (Blueprint $table): void {
                $table->id();
                $table->foreignId('note_id')->constrained(TableName::resolve('notes'))->cascadeOnDelete();
                $table->morphs('assignee');
                $table->nullableMorphs('assigned_by');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['note_id', 'assignee_type', 'assignee_id'], TableName::namedIndex('note_assignments', 'note_assignment_unique'));
                $table->index(['assignee_type', 'assignee_id', 'completed_at'], TableName::namedIndex('note_assignments', 'note_assignment_user_index'));
            });
        }

        if (! TableName::existsAndIsOwned('note_mentions', basename(__FILE__, '.php'))) {
            Schema::create(TableName::resolve('note_mentions'), function (Blueprint $table): void {
                $table->id();
                $table->foreignId('note_id')->constrained(TableName::resolve('notes'))->cascadeOnDelete();
                $table->morphs('mentioned');
                $table->nullableMorphs('mentioned_by');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->unique(['note_id', 'mentioned_type', 'mentioned_id'], TableName::namedIndex('note_mentions', 'note_mention_unique'));
                $table->index(['mentioned_type', 'mentioned_id', 'read_at'], TableName::namedIndex('note_mentions', 'note_mention_user_index'));
            });
        }

        if (! TableName::existsAndIsOwned('note_reminders', basename(__FILE__, '.php'))) {
            Schema::create(TableName::resolve('note_reminders'), function (Blueprint $table): void {
                $table->id();
                $table->foreignId('note_id')->constrained(TableName::resolve('notes'))->cascadeOnDelete();
                $table->timestamp('due_at')->nullable()->index();
                $table->string('timezone')->default('UTC');
                $table->string('recurrence')->default(NoteReminderRecurrence::None->value)->index();
                $table->timestamp('next_due_at')->nullable()->index();
                $table->timestamp('last_notified_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->unique('note_id', TableName::namedIndex('note_reminders', 'note_reminder_note_unique'));
                $table->index(['next_due_at', 'completed_at', 'cancelled_at'], TableName::namedIndex('note_reminders', 'note_reminder_next_due_active_index'));
                $table->index(['due_at', 'completed_at', 'cancelled_at'], TableName::namedIndex('note_reminders', 'note_reminder_due_active_index'));
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(TableName::resolve('note_reminders'));
        Schema::dropIfExists(TableName::resolve('note_mentions'));
        Schema::dropIfExists(TableName::resolve('note_assignments'));
        Schema::dropIfExists(TableName::resolve('notes'));
    }
};
