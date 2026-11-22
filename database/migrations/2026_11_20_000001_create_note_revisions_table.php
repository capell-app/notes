<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (TableName::existsAndIsOwned('note_revisions', basename(__FILE__, '.php'))) {
            return;
        }

        if (! Schema::hasTable(TableName::resolve('notes'))) {
            return;
        }

        Schema::create(TableName::resolve('note_revisions'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('note_id')->constrained(TableName::resolve('notes'))->cascadeOnDelete();
            // The body this revision replaced, encrypted like the note body itself.
            $table->text('body');
            $table->nullableMorphs('edited_by');
            $table->timestamps();

            $table->index(['note_id', 'id'], TableName::namedIndex('note_revisions', 'note_revisions_note_index'));
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableName::resolve('note_revisions'));
    }
};
