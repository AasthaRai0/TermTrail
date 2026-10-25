# TermTrail AI

> **Understand the contract. Manage every commitment.**
> AI-assisted contract intelligence and lifecycle management for PHP + MySQL (XAMPP).
> *Informational assistance only — not legal advice. AI findings are suggestions that need human review.*

Upload contracts, get a plain-English summary and evidence-checked risk findings, extract dates and payments, approve suggested obligations, track deadlines/payments/renewals on a calendar, ask questions with the Contract Copilot, compare document versions, and export reports — all running locally on **XAMPP** with no Python, Node.js or separate frontend server.

## Contents
1. [Features](#1-features) · 2. [Requirements](#2-requirements) · 3. [Install on Windows (XAMPP)](#3-install-on-windows-xampp) · 4. [Optional setup: AI, reminders, email](#4-optional-setup-ai-reminders-email) · 5. [Try it in 10 minutes](#5-try-it-in-10-minutes) · 6. [Roles](#6-roles-and-permissions) · 7. [How the AI parts work](#7-how-the-ai-parts-work) · 8. [API reference](#8-api-reference) · 9. [Tests](#9-tests) · 10. [Troubleshooting](#10-troubleshooting) · 11. [Security & production notes](#11-security--production-notes) · 12. [What was tested](#12-what-was-tested-and-what-was-not) · 13. [Limitations](#13-limitations)

## 1. Features
| Area | What you get |
|---|---|
| **Accounts** | Register/login/logout, hashed passwords, session regeneration, CSRF on every state-changing request, login throttling (5 failures / 15 min), password change, light/dark theme. Admin can create users, change roles, deactivate users, reset passwords and close public sign-up. |
| **Contracts** | PDF/DOCX/TXT upload (validated by extension, real MIME type and content signature; random private filenames; authorized downloads only), manual entry, edit, archive/restore, delete, review notes, full-text search that includes document text, filters, sorting, pagination. |
| **Counterparties** | CRUD, archive, safe delete (blocked while contracts are linked), related contracts. |
| **Dashboard** | Live cards, three charts, recent contracts, upcoming deadlines (expiries, notice deadlines, obligations, payments), recent activity; every card/chart/row links to a filtered view. |
| **AI analysis** | Bounded, resumable pipeline (≤12 sections ≈ 21,000 words): summary, 14 clause types, risk findings, dates, amounts, suggested obligations/payments. Every quotation is checked against the document. Transparent 0-100 heuristic risk score. |
| **Human review** | Approve (with edits) / reject suggestions; mark findings reviewed or dismissed with comments; apply verified key dates. Nothing becomes a commitment automatically. Approver and time recorded. |
| **Copilot** | Questions answered only from retrieved passages (embeddings + cosine in PHP, or FULLTEXT/keywords), verified quotations, "Section N of M" sources, honest "not found" answers. |
| **No-API-key mode** | Everything except AI works; basic regex date/amount/notice suggestions; Copilot shows relevant excerpts; nothing is presented as AI output. |
| **Lifecycle** | Obligations (recurrence, priority, owner, status history), payments (per-currency totals), renewals + decisions, FullCalendar, in-app notifications, reminder job for Windows Task Scheduler, optional email. |
| **Versions** | Upload new versions (history kept), side-by-side-style diff with added (green) / removed (red) / unchanged text, word-level highlights, sensitive-term flags (payment, renewal, termination, liability), optional AI change summary, saved comparison history. |
| **Clause library** | Searchable shared templates with plain-English explanations; clearly labelled reference material. |
| **Reports** | Contract summary, risk findings, upcoming expirations, obligations, payments, counterparties, activity — preview, **CSV** (formula-injection safe) and **PDF** (timestamp, filters, page numbers, disclaimers). |
| **Audit log** | Admin screen + CSV export; secrets are never recorded. |

## 2. Requirements
* Windows with **XAMPP** (PHP 8.1+ — 8.2 recommended, Apache, MySQL/MariaDB) and **Composer**.
* PHP extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `curl`, `openssl`, `gd` (dompdf images), `zlib`, `iconv`.
* Internet access for the front-end libraries (Bootstrap, Lucide, Chart.js, FullCalendar load from CDNs) and, if you enable them, Gemini and SMTP.

## 3. Install on Windows (XAMPP)
1. **Start XAMPP** → click *Start* for **Apache** and **MySQL**.
2. **Copy the project** to `C:\xampp\htdocs\termtrail` (the folder name matters for the default URL).
3. **PHP extensions** — open `C:\xampp\php\php.ini`, make sure these lines have no leading `;`, then restart Apache:
   ```
   extension=curl   extension=fileinfo   extension=gd   extension=mbstring   extension=openssl   extension=pdo_mysql   extension=zip
   ```
   For uploads up to 10 MB keep `upload_max_filesize` ≥ 12M and `post_max_size` ≥ 14M (XAMPP defaults of 40M are fine). `max_execution_time=60` is plenty — the AI pipeline works in small steps.
4. **Install Composer** (https://getcomposer.org), open PowerShell and run:
   ```powershell
   cd C:\xampp\htdocs\termtrail
   composer install
   ```
   (For a deployed copy without test tools use `composer install --no-dev`.)
5. **Create the database** — open http://localhost/phpmyadmin → *New* → name `termtrail_db`, collation `utf8mb4_unicode_ci` → *Create*.
6. **Import the schema** — select `termtrail_db` → *Import* → choose `database/schema.sql` → *Go*. (It is safe to re-run; it never drops data.)
7. **(Optional) demo data** — *Import* `database/seed.sql` into the same database. It adds fictional counterparties, contracts, clauses, risk findings, obligations, payments, notifications and clause templates with dates relative to today. It is safe to import twice and never changes existing data.
   **Demo logins — change or deactivate these before real use:**
   `demo@termtrail.test` / `Demo@12345` (admin) · `viewer@termtrail.test` / `Demo@12345` (read-only reviewer).
8. **Configure** — in PowerShell: `Copy-Item .env.example .env` and edit `.env`. Defaults match XAMPP (`root`, empty password). Never commit `.env`.
9. **Folders** — `storage\contracts`, `storage\extracted_text`, `storage\reports` and `storage\logs` already exist; XAMPP can write to them. `storage\` and `database\` contain an `.htaccess` that blocks direct web access; files are only served through the authorized download endpoints.
10. **Check the install**: `php tools\check.php` (verifies files, routes and syntax; "problems: 0" is what you want). Use `C:\xampp\php\php.exe tools\check.php` if `php` is not on your PATH.
11. **Open** http://localhost/termtrail/ . If you did not import the demo data, click *Create an account* — **the first account becomes the administrator**; later sign-ups are managers who only see their own records (the admin can close sign-up under *Users*).
12. **Try the features** with the walkthrough in section 5.
13. **Scheduled reminders** are *not active* until you set up the Task Scheduler job (section 4). **AI** is not active until you add a Gemini key.

If your folder is not `termtrail`, change `APP_URL` in `.env` and the path in the first `RedirectMatch` line of `.htaccess`.

## 4. Optional setup: AI, reminders, email
### AI analysis & Copilot (Gemini)
1. Create a key at https://aistudio.google.com/apikey (a free tier exists; limits and pricing are Google's).
2. In `.env`: `GEMINI_API_KEY=your-key`. Leave `GEMINI_MODEL` blank for the built-in default (`gemini-2.0-flash`) or set a current model name from Google AI Studio.
3. For semantic Copilot search also set `EMBEDDING_API_KEY` (may be the same key) and re-run the analysis.
4. Check **Settings → AI service**. If it says *Not configured* the app uses the no-key fallback described above.

### Reminders (Windows Task Scheduler)
Reminders are created only when `cron\reminders.php` runs. Nothing runs automatically. Test it once:
```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\termtrail\cron\reminders.php
```
Then schedule it daily (PowerShell):
```powershell
schtasks /Create /SC DAILY /ST 08:00 /TN "TermTrail Reminders" /TR "C:\xampp\php\php.exe C:\xampp\htdocs\termtrail\cron\reminders.php"
schtasks /Run /TN "TermTrail Reminders"
```
Open **Settings → Reminders** (admin): *Last run* should show the current time. The PC and MySQL must be running at the scheduled time (in the task's *Settings* tab tick "Run task as soon as possible after a scheduled start is missed"). Admins can also click *Run reminder check now*.
Reminder windows (each fires once): contract expiry 30/14/7/1 days, renewal notice deadline 30/14/7/1, payment due 7/3/1 and when overdue, obligation due 7/1 and when overdue. Changing a date re-arms its reminders.

### Email (optional)
Fill `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION` (`tls`, `ssl` or `none`), `SMTP_FROM` in `.env` (Gmail needs an *App Password*). Email only goes out with reminders; a mail failure never blocks in-app notifications.

## 5. Try it in 10 minutes
1. **Contracts → New contract**: fill the form and attach a text-based PDF/DOCX/TXT. Open it: the *Overview* shows the file and extraction status (scanned PDFs are reported — OCR is not supported).
2. **AI analysis tab → Run analysis**: watch the progress bar; review the summary, risk score ("How is this calculated?"), findings (verified vs. unverified quotations) and suggestions. *Review & approve* a suggestion, dismiss a finding, apply a suggested date.
3. **Copilot tab**: click *When can either party terminate…* or type your own question; check the "Section N of M" sources.
4. **Versions tab**: upload a revised file with a note, pick two versions, *Compare*.
5. **Obligations / Payments / Renewals / Calendar**: add items, mark payments paid, record a renewal decision, click calendar events.
6. **Reports**: preview *Upcoming expirations*, download CSV and PDF. **Clause library**: search and copy a template.
7. **Admin** (*Users*, *Audit log*): create a user, change a role, read the audit trail.

## 6. Roles and permissions
| | Admin | Manager | Viewer |
|---|---|---|---|
| See contracts | all | own + assigned-reviewer | assigned-reviewer only |
| Create/edit/delete contracts, obligations, payments; run analysis; approve suggestions; upload versions | all | **own** contracts | – |
| Review findings, add notes | all | own + assigned | – |
| Ask Copilot, compare versions, run reports | ✓ | ✓ | ✓ (activity report excluded) |
| Clause library | read + manage all | read + manage own | read |
| Users, settings, audit log, run reminders | ✓ | – | – |
All of this is enforced on the server for every request; hidden buttons are only a convenience. Records of other users are reported as "not found".

## 7. How the AI parts work
* **Pipeline:** each *step* request does one unit of work (one LLM call, one embedding batch, or the save), so nothing is long-running. State is stored in `processing_jobs`; a failed run keeps earlier results and *Resume* continues without repeating finished sections. Double-clicks reuse the running job and a database lock prevents two steps at once.
* **Validation & evidence:** replies must be JSON and are validated field by field. Quotations are searched for in the extracted text; findings show **verified / NOT found** badges. Findings without a quotation are labelled *AI assessment*.
* **Risk score:** `min(100, Σ weight × factor)` — High 25, Medium 10, Low 3; factor 1.0 for detected + verified findings, else 0.6; dismissed findings excluded. 0-29 Low, 30-59 Medium, 60-100 High. A heuristic indicator, not a legally validated score.
* **Untrusted text:** contract text and model replies are treated as data. Prompts fence the text and instruct the model to ignore instructions inside it; the server never acts on model output other than validated fields; everything is rendered as plain text.
* **Limits:** 20 analysis starts and 30 Copilot questions per user per hour; 30 uploads per hour; 10 MB per file.

## 8. API reference
Base: `backend/api/index.php?r=<route>`. Send JSON (`Content-Type: application/json`) or multipart for uploads. Every `POST` needs header `X-CSRF-Token` (value of `<meta name="csrf-token">` on any page). Responses: `{"success":true,…}` or `{"success":false,"message":"…","errors":{"field":"…"}}` with 401 (sign in), 403 (role), 404 (not found/not yours), 409 (conflict), 413, 419 (CSRF), 422 (validation), 429 (limits), 501 (PDF library missing).

| Group | Routes |
|---|---|
| Auth | `POST auth/register · login · logout · password · profile` |
| Dashboard | `GET dashboard/stats` |
| Contracts | `GET contracts/list · get · options · download · text` · `POST contracts/create · update · file · archive · restore · delete · notes` |
| Counterparties | `GET counterparties/list · get · options` · `POST counterparties/create · update · archive · restore · delete` |
| Users | `GET users/options` |
| Obligations | `GET obligations/list · get` · `POST obligations/create · update · status · delete` |
| Payments | `GET payments/list · get · summary` · `POST payments/create · update · status · delete` |
| Renewals / Calendar | `GET renewals/list` · `POST renewals/decision` · `GET calendar/events` |
| Notifications / reminders | `GET notifications/list` · `POST notifications/read · read_all` · `GET reminders/status` · `POST reminders/run` (admin) |
| AI | `GET ai/status` · `POST analysis/start · step · apply_date` · `GET analysis/status · get` · `POST suggestions/approve · reject` · `POST findings/review` |
| Copilot | `POST copilot/ask · clear` · `GET copilot/history` |
| Versions | `GET versions/list · download · comparison` · `POST versions/upload · compare` |
| Clause library | `GET templates/list · get` · `POST templates/create · update · archive · restore` |
| Reports | `GET reports/types · data · export` (`type=`, `format=csv|pdf`, plus filters) |
| Admin | `GET admin/users · audit` · `POST admin/users_create · users_role · users_active · users_password · settings` |

Example (PowerShell, after signing in the browser you can copy the cookie; or use the UI): all routes accept the filters you see in the matching screen, e.g. `contracts/list&status=active&quick=expiring&sort=expiry_date&dir=asc&page=2`.

## 9. Tests
```powershell
composer install                 # installs PHPUnit (dev dependency)
vendor\bin\phpunit               # or: composer test
php tools\check.php              # project self-check (files, routes, includes, syntax)
```
PHPUnit uses its **own database** `termtrail_test` (created automatically; settings in `phpunit.xml`) and **refuses to run** unless the database name ends in `_test`, so your real data is never touched. It covers: authentication and password verification, authorization and ownership, contract validation, upload validation, risk score, AI JSON validation, evidence verification, obligation approval and creation, date/amount parsing, version text comparison, CSV formula escaping, unauthorized file access, reminder windows, lifecycle statuses, vectors and report scoping.

## 10. Troubleshooting
| Symptom | Fix |
|---|---|
| Apache will not start (port 80 in use) | Skype/IIS/other server: stop it, or change Apache's port in XAMPP *Config → httpd.conf*, then set `APP_URL` (e.g. `http://localhost:8080/termtrail`). |
| MySQL will not start | Another MySQL service is running, or the port is taken: stop it or change the port in `my.ini` and `DB_PORT`. |
| "Database connection failed" | MySQL is stopped, `.env` values are wrong, or `termtrail_db`/`schema.sql` was not created/imported. |
| Blank page or HTTP 500 | Read `storage\logs\php-error.log`. Set `APP_ENV=development` in `.env` to see errors on screen (never in production). |
| `composer` not recognised | Reopen PowerShell after installing Composer, or use the full path to `composer.bat`. |
| `composer install` fails on an extension | Enable `zip`, `gd`, `mbstring`, `iconv` in `php.ini`; restart Apache. |
| Styles, icons, charts or calendar missing | CDN blocked or offline; check internet/proxy. |
| Upload fails: "larger than the server limit" | Raise `upload_max_filesize` and `post_max_size` in `php.ini`; restart Apache. |
| PDF upload says "support is not installed" / PDF export HTTP 501 | Run `composer install` (smalot/pdfparser, dompdf). |
| DOCX: zip extension missing | Enable `extension=zip`. |
| "No selectable text was found" | The PDF is scanned/image-based; OCR is not supported. Upload a text-based file. |
| AI: "key is invalid", "model not found", "rate limit" | See section 4; wait a minute and click *Resume*; check `GEMINI_MODEL`. |
| "Could not reach the AI provider" | Internet/proxy/firewall; PHP `curl` and `openssl` enabled. |
| No notifications | The reminder job has not run (*Settings → Reminders* shows "Never"). Run it or schedule the task. |
| Emails not arriving | SMTP values/port, app password, `composer install`; errors are in `php-error.log`. |
| Cannot write files | Right-click `storage` → Properties → make sure your Windows user can modify it. |
| 403 on a page | You are signed in with a role that cannot open it (e.g. admin-only pages). |
| Locked out as the only admin | Import the demo seed for a demo admin, or in phpMyAdmin set `users.role='admin'` for your user. |

## 11. Security & production notes
* All SQL uses prepared PDO statements; output is escaped; CSRF tokens protect state-changing calls; uploads are validated by extension, real MIME type and signature, stored under random names in a protected folder and served only via authorized endpoints; the Gemini/SMTP secrets stay in `.env` on the server; no stack traces are shown when `APP_ENV` is not `development`.
* **For a real deployment:** serve over **HTTPS** (session cookies automatically become `Secure`), set `APP_ENV=production`, change/deactivate the demo users, use a dedicated MySQL user with a strong password (not `root`), keep `.env` and `storage/` outside the public web root if you can (otherwise keep the provided `.htaccess` rules and test that `http://…/termtrail/.env` and `…/storage/…` return 403), back up the database and `storage/`, and keep PHP and Composer packages updated.
* The app stores and sends contract text to the AI provider only when you enable AI. Review your organization's data policy before analysing confidential contracts.

## 12. What was tested (and what was not)
Built and verified in a Linux sandbox with PHP 8.3, MariaDB 10.11, PHPUnit 9.6, the real `dompdf 2.0.7`, `jfcherng/php-diff 6.16.2`, `smalot/pdfparser`, `PHPMailer`, jsdom (with the real FullCalendar bundle) and `poppler`'s `pdftotext` to read generated PDFs.

| Suite | Result |
|---|---|
| PHPUnit (own test DB) | **101 tests, 358 assertions pass** |
| Pure-logic unit scripts | 44 / 44 |
| `tools/check.php` self-check (+ mutation test with 5 injected faults, all detected) | 393 checks, 0 problems |
| API checks — Phase 2 contracts/uploads, Phase 3 lifecycle (94 + 10 email), Phase 4 AI (122), Phase 5 (101), seed/demo (18) | all pass |
| Front-end checks (jsdom, real server) — Phase 2 (54), Phase 3 (73), Phase 4 (69), Phase 5 (66) | all pass |

These exercised: uploads of real TXT/DOCX/PDF and attack files, role and cross-user isolation, CSRF, throttling, status transitions, recurrence, reminders (including real SMTP delivery to a local test server), the AI pipeline against a **local mock of the Gemini API** (including failures, resume, fake quotations, prompt-injection text, embeddings), approvals, version diffs, CSV/PDF exports, admin actions, the seed data, and the main UI flows.

**Not tested — please check on your machine:**
* The **real Gemini service** (no key/network here): request formats follow Google's documented REST API but were only verified against the mock. Real answer quality, model names and limits are unverified.
* **XAMPP on Windows itself**: your `php.ini`, Apache `.htaccess` rules, file permissions, Windows **Task Scheduler**, and `composer install` on Windows.
* **Real browsers**: layout on phones/tablets, CDN loading, Chart.js rendering and chart click-through, the browser file picker, keyboard/screen-reader behaviour (accessibility attributes are present but not audited).
* **Real SMTP providers** (Gmail etc.), and documents from real-world generators — DOCX files saved by Microsoft Word and complex PDFs were represented only by small synthetic files.
* PHPUnit **10.5** (installed by Composer) — tests were run on 9.6 and avoid features that differ.

## 13. Limitations
No OCR (scanned/image PDFs cannot be read) · analysis covers the first ~21,000 words · no email-based "forgot password" (an admin resets passwords) · no two-factor sign-in · one currency per payment (totals are shown per currency, never converted) · AI output can be wrong — that is why quotations are verified, findings are labelled and humans approve commitments · not a substitute for legal advice.
