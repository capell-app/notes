<?php

declare(strict_types=1);

return [
    'actions' => [
        'complete_assignment' => 'Zuweisung abschließen',
        'cancel_edit' => 'Abbrechen',
        'create' => 'Notiz hinzufügen',
        'edit' => 'Bearbeiten',
        'hide_history' => 'Verlauf ausblenden',
        'save_edit' => 'Änderungen speichern',
        'show_history' => 'Verlauf',
        'open_notes' => 'Notizen öffnen',
        'reopen' => 'Wieder öffnen',
        'resolve' => 'Als erledigt markieren',
    ],
    'assigned_to_me' => 'Mir zugewiesen',
    'due_today' => 'Heute fällig',
    'edit_label' => 'Notiz bearbeiten',
    'empty_inbox' => 'Keine Notizen entsprechen dieser Ansicht.',
    'fields' => [
        'assignees' => 'Zuweisen an',
        'body' => 'Notiz',
        'mentions' => 'Erwähnen',
        'reminder_due_at' => 'Erinnerung fällig am',
        'reminder_recurrence' => 'Erinnerung wiederholen',
        'reminder_timezone' => 'Zeitzone der Erinnerung',
        'visibility' => 'Sichtbarkeit',
    ],
    'filters' => [
        'all' => 'Alle',
        'status' => 'Status',
        'assigned' => 'Mir zugewiesen',
        'mentions' => 'Ungelesene Erwähnungen',
        'dueToday' => 'Heute fällig',
        'overdue' => 'Überfällig',
    ],
    'history' => [
        'edited_by' => 'Bearbeitet von :user',
        'heading' => 'Bearbeitungsverlauf',
    ],
    'inbox_title' => 'Notizen',
    'labels' => [
        'assigned' => 'Zugewiesen',
        'author' => 'Autor',
        'mentioned' => 'Erwähnt',
        'record_fallback' => ':record #:id',
        'subject' => 'Betreff',
        'unknown_subject' => 'Unbekannter Betreff',
        'unknown_user' => 'Unbekannter Benutzer',
    ],
    'mentions' => 'Erwähnungen',
    'notifications' => [
        'created' => 'Notiz hinzugefügt.',
    ],
    'notification_mail' => [
        'assigned' => [
            'line' => 'Ihnen wurde eine Capell-Notiz zugewiesen.',
            'subject' => 'Sie haben eine neue Zuweisung einer Capell-Notiz',
        ],
        'mentioned' => [
            'line' => 'Sie wurden in einer Capell-Notiz erwähnt.',
            'subject' => 'Sie wurden in einer Capell-Notiz erwähnt',
        ],
        'reminder_unsent' => [
            'line' => 'Eine Notizerinnerung konnte nicht gesendet werden, da kein aktiver Zugewiesener Zugriff auf den Datensatz hat. Prüfen Sie sie unter Notizen und bitten Sie dann Ihre Administratorin oder Ihren Administrator, den Zugriff wiederherzustellen oder die Zuweisung und Erinnerung über die Integration zu aktualisieren, die sie erstellt hat.',
            'subject' => 'Eine Capell-Notizerinnerung erfordert Ihre Aufmerksamkeit',
        ],
        'reminder' => [
            'line' => 'Eine Capell-Notizerinnerung ist fällig.',
            'subject' => 'Eine Capell-Notizerinnerung ist fällig',
        ],
    ],
    'overdue' => 'Überfällig',
    'reminder_recipients' => 'Erinnerungen benachrichtigen aktive Zugewiesene mit Zugriff auf den Datensatz. Weisen Sie sich die Notiz selbst zu, um eine persönliche Erinnerung zu erhalten.',
    'reminder_unsent' => 'Erinnerung nicht gesendet: kein berechtigter Zugewiesener. Bitten Sie Ihre Administratorin oder Ihren Administrator, den Zugriff auf den Datensatz wiederherzustellen oder die Zuweisung und Erinnerung über die Integration zu aktualisieren, die sie erstellt hat.',
    'next_reminder' => 'Nächste Erinnerung: :local (:utc UTC).',
    'pagination' => 'Notizseiten',
    'previous' => 'Zurück',
    'next' => 'Weiter',
    'recent_notes' => 'Notizverlauf',
    'recurrence' => [
        'daily' => 'Täglich',
        'monthly' => 'Monatlich',
        'none' => 'Keine',
        'weekly' => 'Wöchentlich',
        'yearly' => 'Jährlich',
    ],
    'status' => [
        'archived' => 'Archiviert',
        'dismissed' => 'Verworfen',
        'open' => 'Offen',
        'resolved' => 'Erledigt',
    ],
    'validation' => [
        'body_max' => 'Die Notiz darf höchstens :max Zeichen lang sein.',
        'body_required' => 'Geben Sie vor dem Speichern eine Notiz ein.',
        'reminder_recipient_required' => 'Weisen Sie mindestens einen Editor mit Zugriff auf den Datensatz zu, der die Erinnerung erhält. Sie können sich selbst zuweisen.',
        'reminders_unavailable' => 'Neue oder ersetzte Erinnerungen sind vorübergehend nicht verfügbar. Wenden Sie sich an Ihre Administratorin oder Ihren Administrator.',
    ],
    'visibility' => [
        'private' => 'Privat',
        'record_editors' => 'Datensatz-Editoren',
    ],
];
