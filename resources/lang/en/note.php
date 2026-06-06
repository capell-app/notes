<?php

declare(strict_types=1);

return [
    'actions' => [
        'complete_assignment' => 'Complete assignment',
        'create' => 'Add note',
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
        'visibility' => 'Visibility',
    ],
    'filters' => [
        'all' => 'All',
        'status' => 'Status',
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
    ],
    'overdue' => 'Overdue',
    'recent_notes' => 'Recent notes',
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
    ],
    'visibility' => [
        'private' => 'Private',
        'record_editors' => 'Record editors',
    ],
];
