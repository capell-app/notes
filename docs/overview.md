# Notes

Status: **Available, schema-owning** · Kind: **package** · Tier: **premium** · Bundle: **collaboration** · Contexts: **admin** · Product group: **Capell Collaboration**

Notes adds contextual notes, assignments, mentions, attention counts, and a user inbox to supported Capell admin records.

## Install

```bash
composer require capell-app/notes
```

The package requires `capell-app/admin`.

## Admin Surfaces

- `NotesInboxPage` at `/admin/notes`.
- Admin user-menu item with attention badge.
- Package models for notes, assignments, mentions, and scheduled reminder records.
- Actions for creating notes, assigning users, marking mentions read, calculating attention counts, and sending due reminder notifications.

## Frontend Surfaces

This package has no public frontend routes or Blade output. It should not add any anonymous public surface.

## Screenshot Plan

- Notes inbox page.
- User-menu notes item with attention badge.
- Empty inbox state.
- Inbox with assigned, mentioned, and lifecycle action states.

## Verification

- Package tests: `vendor/bin/pest packages/notes/tests --configuration=phpunit.xml`.
- Harness install: `composer require capell-app/notes:4.x-dev -W`, then `php artisan package:discover --ansi` and `php artisan migrate --graceful --ansi`.

## Known Risks

- Marketplace screenshots include Capell runner captures for the populated inbox, attention counts, empty state, and dark-mode workflow.
- The user-menu badge capture contract still targets `/admin/notes`; promote a dedicated menu-open capture once the runner supports that state.
- The package has no public frontend surface; future render hooks should include public-safety tests before documentation screenshots are added.
