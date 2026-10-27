<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('note_reminders')) {
            return;
        }

        if (! Schema::hasColumn('note_reminders', 'schedule_version')) {
            Schema::table('note_reminders', function (Blueprint $table): void {
                // Existing timezone strings were descriptive, never scheduling inputs.
                $table->unsignedSmallInteger('schedule_version')->default(1);
            });
        }

        if (! Schema::hasColumn('note_reminders', 'delivery_failed_at')) {
            Schema::table('note_reminders', function (Blueprint $table): void {
                $table->timestamp('delivery_failed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['schedule_version', 'delivery_failed_at'] as $column) {
            if (Schema::hasTable('note_reminders') && Schema::hasColumn('note_reminders', $column)) {
                Schema::table('note_reminders', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
