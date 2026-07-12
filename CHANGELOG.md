# Changelog

All notable changes to `capell-app/notes` will be documented in this file.

## Unreleased

- Prepared package metadata and documentation for ongoing Capell 4.x package work.

## 2026-06-03

- Replaced the placeholder `NotesHealthCheck` with real diagnostics that verify the notes storage tables exist and the notes models are registered in the morph map, and aligned the manifest health-check label with what the probe asserts.
- Rewrote the marketplace summary, description, and composer description to lead with buyer value and to stop advertising the not-yet-functional reminders capability.
- Added a 5,000-character maximum length validation to note creation to guard storage and admin inbox render performance.
