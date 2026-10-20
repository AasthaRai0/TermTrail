# TermTrail AI — Phase 1 (Foundation)

> Understand the contract. Manage every commitment.
> Informational assistance only — not legal advice.

**Phase 1 includes:** full MySQL schema (22 tables), registration/login/logout, roles (admin/manager/viewer), CSRF protection, login throttling, password change, light/dark theme, responsive app shell, and a live dashboard (real SQL counts + Chart.js charts).
Contracts, obligations, payments, AI analysis, Copilot, reports and tests arrive in the next phases.

## Setup on Windows (XAMPP)

1. Install XAMPP and start **Apache** and **MySQL** from the control panel.
2. Copy this folder to `C:\xampp\htdocs\termtrail`.
3. In phpMyAdmin (`http://localhost/phpmyadmin`) create database `termtrail_db` (collation `utf8mb4_unicode_ci`), then **Import → database/schema.sql**.
4. Copy `.env.example` to `.env` (PowerShell: `Copy-Item .env.example .env`). Defaults match XAMPP (`root`, empty password).
5. Open `http://localhost/termtrail/` and click **Create an account**. The **first** account becomes the admin; later sign-ups become managers who only see their own records.

`composer install` is not needed yet; it is required from Phase 2 (PDF/DOCX parsing, reports).

## Routes (Phase 1)
All API calls go to `backend/api/index.php?r=<route>`. State-changing calls need header `X-CSRF-Token` (read from the page's `<meta name="csrf-token">`).

| Method | Route | Body | Notes |
|---|---|---|---|
| POST | auth/register | name,email,password,password_confirm | 201 + redirect |
| POST | auth/login | email,password | 5 failures/15 min → 429 |
| POST | auth/logout | – | |
| POST | auth/password | current_password,new_password,new_password_confirm | |
| POST | auth/profile | name,theme | |
| GET | dashboard/stats | – | cards, charts, recent contracts, deadlines, activity |

Errors: `{"success":false,"message":"...","errors":{"field":"..."}}` with 401/403/419/422/429.

## Troubleshooting
- **"Database connection failed"** — MySQL not started, or wrong values in `.env`; or the database was not created/imported.
- **Blank page / 500** — see `storage/logs/php-error.log`.
- **Styles/icons missing** — the UI loads Bootstrap, Lucide and Chart.js from CDNs, so internet access is required.
- **Folder not named `termtrail`** — update `APP_URL` in `.env` and the path in `.htaccess`.

## What was tested vs. not
Tested here (PHP 8.3 + MariaDB 10.11, PHP built-in server): schema import, PHP/JS syntax, registration, validation errors, CSRF rejection, login, throttling, logout, role assignment, dashboard API, audit entries.
**Not tested (needs your XAMPP):** Apache `.htaccess` rules, browser UI rendering/CDN loading, and visual layout on real devices.
