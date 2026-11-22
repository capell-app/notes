# Changelog

All notable changes to `capell-app/notes` will be documented in this file.

## Unreleased

- Added note edit history: `UpdateNoteBodyAction` lets a note's author change its body and keeps each replaced body, encrypted, in a new `note_revisions` table, readable newest first through `Note::revisions()`. The Notes inbox now has an **Edit** button on a note's author and a **History** button, shown once a note has been edited, that lists who changed it, when, and what it said before. History is visible only to users who can view the note.
- The Add note form no longer offers reminder fields while new reminders are switched off for the staged rollout, so a note with a reminder can no longer fail and roll back its assignments and mentions. Reminders are available once the rollout flag is enabled.
- Prepared package metadata and documentation for ongoing Capell 0.0.x package work.

## 2026-06-03

- Replaced the placeholder `NotesHealthCheck` with real diagnostics that verify the notes storage tables exist and the notes models are registered in the morph map, and aligned the manifest health-check label with what the probe asserts.
- Rewrote the marketplace summary, description, and composer description to lead with buyer value and to stop advertising the not-yet-functional reminders capability.
- Added a 5,000-character maximum length validation to note creation to guard storage and admin inbox render performance.
