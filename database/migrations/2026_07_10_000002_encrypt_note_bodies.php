<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table): void {
            $table->mediumText('body')->change();
        });

        DB::table('notes')->orderBy('id')->each(static function (object $note): void {
            DB::table('notes')->where('id', $note->id)->update(['body' => Crypt::encryptString($note->body)]);
        });
    }

    public function down(): void
    {
        DB::table('notes')->orderBy('id')->each(static function (object $note): void {
            DB::table('notes')->where('id', $note->id)->update(['body' => Crypt::decryptString($note->body)]);
        });

        Schema::table('notes', function (Blueprint $table): void {
            $table->text('body')->change();
        });
    }
};
