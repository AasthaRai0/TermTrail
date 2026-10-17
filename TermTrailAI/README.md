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

## Update 3
Added: contract edit and permanent delete, profile and password change (`settings.php`), printable report (`report.php`, use Print > Save as PDF; Dompdf is not used), dashboard charts and upcoming deadlines, mobile menu, optional `STORAGE_DIR` in `.env` to keep uploads outside htdocs (recommended).

## Verification done
Run on PHP 8.3 + MariaDB 10.11 with the built-in PHP server and curl: all PHP files lint clean; register, login, upload, analysis, obligation confirm, payments (partial payment), exports, edit/delete, report and settings return expected results; a second user gets 404 on another user's contract, report, download and analysis; a bad CSRF token gets 419; a .exe and a fake PDF are rejected. Not tested: Apache/XAMPP itself, PDF and DOCX parsing with real files, mobile layout in a browser, no automated test suite yet.
