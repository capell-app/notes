<?php

declare(strict_types=1);

return [
    'actions' => [
        'complete_assignment' => 'Complete assignment',
        'create' => 'Add note',
        'open_notes' => 'Open Notes',
        'reopen' => 'Reopen',
        'resolve' => 'Resolve',
    ],
    'assigned_to_me' => 'Assigned to me',
    'due_today' => 'Due today',
    'empty_inbox' => 'No notes match this view.',
    'fields' => [
        'assignees' => 'Assign to',
        'body' => 'Note',
        'mentions' => 'Mention',
        'reminder_due_at' => 'Reminder due',
        'reminder_recurrence' => 'Reminder repeats',
        'reminder_timezone' => 'Reminder timezone',
        'visibility' => 'Visibility',
    ],
    'filters' => [
        'all' => 'All',
        'status' => 'Status',
        'assigned' => 'Assigned to me',
        'mentions' => 'Unread mentions',
        'dueToday' => 'Due today',
        'overdue' => 'Overdue',
    ],
    'inbox_title' => 'Notes',
    'labels' => [
        'assigned' => 'Assigned',
        'author' => 'Author',
        'mentioned' => 'Mentioned',
        'record_fallback' => ':record #:id',
        'subject' => 'Subject',
        'unknown_subject' => 'Unknown subject',
        'unknown_user' => 'Unknown user',
    ],
    'mentions' => 'Mentions',
    'notifications' => [
        'created' => 'Note added.',
    ],
    'notification_mail' => [
        'assigned' => [
            'line' => 'You have been assigned a Capell note.',
            'subject' => 'You have a new Capell note assignment',
        ],
        'mentioned' => [
            'line' => 'You have been mentioned in a Capell note.',
            'subject' => 'You were mentioned in a Capell note',
        ],
        'reminder_unsent' => [
            'line' => 'A note reminder could not be sent because no active assignee has record access. Review it in Notes, then ask your administrator to restore access or update the assignment and reminder through the integration that created it.',
            'subject' => 'A Capell note reminder needs attention',
        ],
        'reminder' => [
            'line' => 'A Capell note reminder is due.',
            'subject' => 'A Capell note reminder is due',
        ],
    ],
    'overdue' => 'Overdue',
    'reminder_recipients' => 'Reminders notify active assignees with record access. Assign yourself for a personal reminder.',
    'reminder_unsent' => 'Reminder unsent: no eligible assignee. Ask your administrator to restore record access or update the assignment and reminder through the integration that created it.',
    'next_reminder' => 'Next reminder: :local (:utc UTC).',
    'pagination' => 'Notes pages',
    'previous' => 'Previous',
    'next' => 'Next',
    'recent_notes' => 'Notes history',
    'recurrence' => [
        'daily' => 'Daily',
        'monthly' => 'Monthly',
        'none' => 'None',
        'weekly' => 'Weekly',
        'yearly' => 'Yearly',
    ],
    'status' => [
        'archived' => 'Archived',
        'dismissed' => 'Dismissed',
        'open' => 'Open',
        'resolved' => 'Resolved',
    ],
    'validation' => [
        'body_max' => 'Keep the note to :max characters or fewer.',
        'body_required' => 'Enter a note before saving.',
        'reminder_recipient_required' => 'Assign at least one editor with record access to receive the reminder. You can assign yourself.',
        'reminders_unavailable' => 'New or replacement reminders are temporarily unavailable. Contact your administrator.',
    ],
    'visibility' => [
        'private' => 'Private',
        'record_editors' => 'Record editors',
    ],
];
