<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(TableName::resolve('notes'), function (Blueprint $table): void {
            $table->mediumText('body')->change();
        });

        DB::table(TableName::resolve('notes'))->orderBy('id')->each(static function (object $note): void {
            DB::table(TableName::resolve('notes'))->where('id', $note->id)->update(['body' => Crypt::encryptString($note->body)]);
        });
    }

    public function down(): void
    {
        DB::table(TableName::resolve('notes'))->orderBy('id')->each(static function (object $note): void {
            DB::table(TableName::resolve('notes'))->where('id', $note->id)->update(['body' => Crypt::decryptString($note->body)]);
        });

        Schema::table(TableName::resolve('notes'), function (Blueprint $table): void {
            $table->text('body')->change();
        });
    }
};
