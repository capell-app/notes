<?php

declare(strict_types=1);

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Notes\Console\DemoCommand;
use Capell\Notes\Console\SendDueNoteRemindersCommand;
use Capell\Notes\Filament\Extenders\Page\CreateNoteResourceHeaderActionExtender;
use Capell\Notes\Filament\Pages\NotesInboxPage;
use Capell\Notes\Health\NotesHealthCheck;
use Capell\Notes\Manifest\NotesAdminActionExtenderContribution;
use Capell\Notes\Manifest\NotesAdminPageContribution;
use Capell\Notes\Manifest\NotesConsoleCommandsContribution;
use Capell\Notes\Manifest\NotesHealthContribution;
use Capell\Notes\Manifest\NotesModelsContribution;
use Capell\Notes\Manifest\NotesReminderScheduleContribution;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteAssignment;
use Capell\Notes\Models\NoteMention;
use Capell\Notes\Models\NoteReminder;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

function notesManifest(): array
{
    return capell_json_file_array(__DIR__ . '/../../capell.json');
}

it('declares implemented notes package contributions', function (): void {
    $manifest = notesManifest();
    $manifestContributions = $manifest['contributes'] ?? [];
    throw_unless(is_array($manifestContributions), RuntimeException::class, 'Notes contributions must be arrays.');
    $contributions = collect($manifestContributions);

    expect($manifest['contributionTraceability']['deferredContributions'])->toBe([])
        ->and($contributions->pluck('class')->all())->toContain(
            NotesAdminPageContribution::class,
            NotesAdminActionExtenderContribution::class,
            NotesModelsContribution::class,
            NotesReminderScheduleContribution::class,
            NotesConsoleCommandsContribution::class,
            NotesHealthContribution::class,
        );

    $adminPage = $contributions->firstWhere('class', NotesAdminPageContribution::class);
    $adminActionExtender = $contributions->firstWhere('class', NotesAdminActionExtenderContribution::class);
    $models = $contributions->firstWhere('class', NotesModelsContribution::class);
    $scheduledJob = $contributions->firstWhere('class', NotesReminderScheduleContribution::class);
    $consoleCommands = $contributions->firstWhere('class', NotesConsoleCommandsContribution::class);
    $healthCheck = $contributions->firstWhere('class', NotesHealthContribution::class);
    throw_unless(is_array($adminPage), RuntimeException::class, 'Expected notes admin page contribution.');
    throw_unless(is_array($adminActionExtender), RuntimeException::class, 'Expected notes admin action extender contribution.');
    throw_unless(is_array($models), RuntimeException::class, 'Expected notes model contribution.');
    throw_unless(is_array($scheduledJob), RuntimeException::class, 'Expected notes scheduled job contribution.');
    throw_unless(is_array($consoleCommands), RuntimeException::class, 'Expected notes console command contribution.');
    throw_unless(is_array($healthCheck), RuntimeException::class, 'Expected notes health check contribution.');

    expect($adminPage['pageClass'])->toBe(NotesInboxPage::class)
        ->and($adminPage['userMenuKey'])->toBe('capell-notes.inbox')
        ->and($adminActionExtender['extenderClass'])->toBe(CreateNoteResourceHeaderActionExtender::class)
        ->and($adminActionExtender['tag'])->toBe('capell-admin:resource-header-actions')
        ->and($models['modelClasses'])->toBe([
            Note::class,
            NoteAssignment::class,
            NoteMention::class,
            NoteReminder::class,
        ])
        ->and($scheduledJob['command'])->toBe('capell:notes:send-due-reminders')
        ->and($manifest['commands']['demo'])->toBe('capell:notes-demo')
        ->and($manifest['commands']['sendDueReminders'])->toBe('capell:notes:send-due-reminders')
        ->and($consoleCommands['commands'])->toBe(['capell:notes-demo', 'capell:notes:send-due-reminders'])
        ->and($consoleCommands['commandClasses'])->toBe([
            DemoCommand::class,
            SendDueNoteRemindersCommand::class,
        ])
        ->and($healthCheck['checkClass'])->toBe(NotesHealthCheck::class)
        ->and(class_implements(NotesAdminPageContribution::class))->toContain(ExtensionContribution::class)
        ->and(class_implements(NotesAdminActionExtenderContribution::class))->toContain(ExtensionContribution::class)
        ->and(class_implements(NotesModelsContribution::class))->toContain(ExtensionContribution::class)
        ->and(class_implements(NotesConsoleCommandsContribution::class))->toContain(ExtensionContribution::class)
        ->and(class_implements(NotesHealthContribution::class))->toContain(ChecksExtensionHealth::class);
});

it('declares the notes storage tables required by package health checks', function (): void {
    $manifest = notesManifest();

    expect($manifest['database']['requiredTables'])->toBe([
        'notes',
        'note_assignments',
        'note_mentions',
        'note_reminders',
    ]);
});
