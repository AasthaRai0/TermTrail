# TermTrail AI (core build)
Contract lifecycle tracker. Informational software, not legal advice.

## Setup (Windows + XAMPP)
1. Install XAMPP (PHP 8.1+), start Apache and MySQL.
2. Copy this folder to `C:\xampp\htdocs\TermTrailAI`.
3. In phpMyAdmin create database `termtrail` (utf8mb4), then import `database/schema.sql`.
4. Copy `.env.example` to `.env` and set DB credentials (the app reads `.env` itself).
5. In the folder run `composer install` (needed only for PDF text extraction). DOCX and TXT work without it. Enable `extension=zip` and `bcmath` in php.ini.
6. Open http://localhost/TermTrailAI/ and register.

## Implemented
Auth (hashed passwords, session regeneration, CSRF), contract library (search, filter, pagination, create, status, archive), secure upload (allowlist, size and content-signature check, random names, storage denied by Apache and served via authenticated `download.php`), text extraction with honest status and manual fallback, obligations (add, status, overdue computed from date), payment tracker (DECIMAL, bcmath), notes, activity log, dashboard from real data.

## Added in update 2
Rule-based clause classification, four-pillar risk findings with real evidence excerpts and review status (`analysis.php`), rule-based obligation suggestions with edit/confirm/reject (only confirmed ones become obligations), version upload and paragraph-level diff with saved history (`compare.php`), CSV export (`export.php`). **Existing databases:** import `database/migration_002.sql` (adds `contract_comparisons`). Analysis is keyword-heuristic, not ML or LLM.

## Not implemented
LLM summary, Copilot/RAG, semantic comparison, risk deltas, PDF reports, calendar view, Python/FastAPI service, reminders/notifications processing, charts, OCR, profile/password change, contract metadata editing UI, seed data, automated tests. Tables for several of these exist in the schema but no code uses them yet.

## Status
Code (including update 2) has not been executed or linted (no PHP runtime in the build environment). Expect small fixes on first run.
