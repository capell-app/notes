<?php

declare(strict_types=1);

namespace Capell\Notes\Health;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteAssignment;
use Capell\Notes\Models\NoteMention;
use Capell\Notes\Models\NoteReminder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class NotesHealthCheck implements ChecksExtensionHealth
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const array MODELS_BY_MORPH_ALIAS = [
        'note' => Note::class,
        'note_assignment' => NoteAssignment::class,
        'note_mention' => NoteMention::class,
        'note_reminder' => NoteReminder::class,
    ];

    /**
     * @var list<string>
     */
    private const array REQUIRED_TABLE_NAMES = [
        'notes',
        'note_assignments',
        'note_mentions',
        'note_reminders',
    ];

    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(): Collection
    {
        $check = new self;

        return collect([
            $check->storageTablesCheck(),
            $check->modelMorphAliasCheck(),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    /**
     * Asserts every notes storage table exists.
     */
    public function storageTablesCheck(): DoctorCheckResultData
    {
        $missingTables = $this->missingTables();

        return new DoctorCheckResultData(
            label: 'Notes storage tables',
            passed: $missingTables === [],
            message: $missingTables === []
                ? 'All notes, assignment, mention, and reminder tables are present.'
                : 'Missing tables: ' . implode(', ', $missingTables) . '.',
            remediation: $missingTables === []
                ? null
                : 'Run the Capell migrations to create the notes storage tables.',
        );
    }

    /**
     * Asserts the notes models are discoverable through the morph map.
     */
    public function modelMorphAliasCheck(): DoctorCheckResultData
    {
        $unregisteredAliases = $this->unregisteredMorphAliases();

        return new DoctorCheckResultData(
            label: 'Notes model morph aliases',
            passed: $unregisteredAliases === [],
            message: $unregisteredAliases === []
                ? 'Note, assignment, mention, and reminder models are registered in the morph map.'
                : 'Unregistered morph aliases: ' . implode(', ', $unregisteredAliases) . '.',
            remediation: $unregisteredAliases === []
                ? null
                : 'Ensure NotesServiceProvider registers the notes models in the morph map.',
        );
    }

    /**
     * @return list<string>
     */
    public function missingTables(): array
    {
        return array_values(array_filter(
            self::REQUIRED_TABLE_NAMES,
            static fn (string $tableName): bool => ! Schema::hasTable($tableName),
        ));
    }

    /**
     * @return list<string>
     */
    public function unregisteredMorphAliases(): array
    {
        $aliases = [];

        foreach (self::MODELS_BY_MORPH_ALIAS as $morphAlias => $modelClass) {
            if (Relation::getMorphedModel($morphAlias) !== $modelClass) {
                $aliases[] = $morphAlias;
            }
        }

        return $aliases;
    }
}
