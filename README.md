# TermTrail

### Contract Lifecycle & Obligation Management Platform

**Every term has a timeline.**

TermTrail is a full-stack web application designed to simplify contract management by helping users organize agreements, track obligations, manage payment commitments, monitor deadlines, and compare contract versions in one place.

Instead of simply analyzing a document, TermTrail focuses on what happens throughout its lifecycle—from uploading an agreement to tracking commitments and reviewing future changes.

---

## Overview

Managing contracts manually can make it difficult to remember payment dates, renewal periods, contractual responsibilities, and important changes between document versions.

TermTrail provides a centralized workspace where users can manage their contracts, organize obligations, monitor deadlines, and maintain a history of important updates.

It also offers optional LLM-assisted clause explanations and obligation suggestions, while keeping core contract-management functionality independent of external AI services.

## Core Features

### 1. Contract Management
- Upload PDF and TXT agreements.
- Add and organize contracts with relevant metadata.
- Search, filter, edit, archive, and manage documents.
- Extract readable text from supported PDF files.
- Track contract status, effective dates, and expiry dates.

### 2. Obligation Tracking
- Create and manage contract-related obligations.
- Assign due dates, priorities, responsible parties, and statuses.
- Monitor pending, completed, and overdue obligations.
- Organize contractual commitments in a chronological timeline.

### 3. Deadline & Renewal Management
- Track contract expiry and renewal dates.
- Manage notice periods and important milestones.
- View upcoming and overdue deadlines.
- Configure reminders and in-app notifications.

### 4. Payment & Financial Tracking
- Record one-time and recurring payment commitments.
- Track instalments, deposits, and other contractual amounts.
- Monitor upcoming payments and payment statuses.
- View financial summaries and monthly commitments.

### 5. Contract Version Comparison
- Maintain previous versions of agreements.
- Compare selected document versions.
- Highlight added and removed text.
- Review potential changes to dates, amounts, and obligations.

### 6. LLM-Assisted Clause Intelligence
- Explain selected clauses in plain language.
- Suggest potential obligations and important dates.
- Flag ambiguous wording and details that may need review.
- Allow users to verify suggestions before saving them.

AI-generated outputs are suggestions and are not treated as verified legal conclusions.

### 7. Review Workspace
- Add private contract notes.
- Maintain review checklists.
- Mark clauses for further review.
- Track review progress and relevant activity.

### 8. Dashboard & Reports
- View contract and obligation summaries.
- Monitor upcoming deadlines and pending payments.
- Explore dashboard charts and activity history.
- Generate downloadable contract summary reports.

---

## Core User Workflow

1. **Register & Login** — Access a personalized workspace.
2. **Add a Contract** — Upload an agreement or enter its details manually.
3. **Review the Document** — Examine extracted text and important contract information.
4. **Confirm Obligations** — Verify suggested details and create trackable commitments.
5. **Set Deadlines** — Configure due dates, payment schedules, and renewal reminders.
6. **Monitor Progress** — Track obligations, payments, and upcoming events from the dashboard.
7. **Manage Changes** — Upload revised versions and compare document changes.
8. **Generate Reports** — Export relevant contract information for reference.

---

## Technology Stack

| Layer | Technologies |
|---|---|
| Frontend | HTML5, CSS3, Bootstrap 5 |
| Client-side functionality | Vanilla JavaScript |
| Backend | Core PHP 8+ |
| Database | MySQL |
| Local development | XAMPP |
| Database administration | phpMyAdmin |
| PDF text extraction | Smalot PDFParser |
| PDF report generation | Dompdf |
| Dashboard visualizations | Chart.js |
| API communication | PHP cURL |
| Dependency management | Composer |
| Version control | Git and GitHub |

---

## Architecture

TermTrail follows a modular, server-rendered PHP architecture.

- **Presentation Layer:** Responsive pages built with HTML, CSS, Bootstrap, and JavaScript.
- **Application Layer:** PHP handles authentication, validation, contract operations, obligation tracking, and business logic.
- **Data Layer:** MySQL stores users, contracts, versions, obligations, deadlines, payments, notes, and notifications.
- **Document Processing Layer:** PDF parsing extracts readable text for review and comparison.
- **Optional AI Layer:** A server-side API integration supports clause explanations and structured suggestions.

Core application features are designed to remain usable when the LLM API is unavailable.

---

## Project Structure

```text
TermTrail/
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       ├── app.js
│       ├── dashboard.js
│       └── validation.js
├── config/
│   ├── app.php
│   └── database.php
├── includes/
│   ├── auth.php
│   ├── csrf.php
│   ├── functions.php
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
├── contracts/
├── obligations/
├── deadlines/
├── payments/
├── review/
├── comparison/
├── intelligence/
├── notifications/
├── reports/
├── profile/
├── database/
│   ├── schema.sql
│   └── seed.sql
├── src/
│   ├── Services/
│   ├── Repositories/
│   └── Validators/
├── storage/
├── composer.json
├── .env.example
├── .gitignore
├── index.php
├── login.php
├── register.php
└── README.md
```

*The structure represents the intended modular architecture. Adjust it to match the files implemented in the repository.*

---

## Getting Started

### Prerequisites

Install the following tools:

- [XAMPP](https://www.apachefriends.org/)
- [Composer](https://getcomposer.org/)
- [Git](https://git-scm.com/)

Ensure that PHP 8+, Apache, and MySQL are available.

### 1. Clone the Repository

Open a terminal and run:

```bash
cd C:\xampp\htdocs
git clone YOUR_REPOSITORY_URL TermTrail
cd TermTrail
```

Replace `YOUR_REPOSITORY_URL` with the actual GitHub repository URL.

### 2. Start XAMPP

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 3. Create the Database

1. Open `http://localhost/phpmyadmin/`.
2. Create a database named `termtrail`.
3. Select the database and open the **Import** tab.
4. Import `database/schema.sql`.

If development seed data is provided, import `database/seed.sql` separately.

### 4. Configure the Database

Update `config/database.php` or the application's environment configuration with your local database settings.

Example local configuration:

```text
DB_HOST=localhost
DB_PORT=3306
DB_NAME=termtrail
DB_USER=root
DB_PASS=
```

These values are examples for a typical local XAMPP installation. Use your actual credentials and never commit real secrets.

### 5. Install Dependencies

From the project directory, run:

```bash
composer install
```

Ensure that the required PHP extensions are enabled and the required packages are declared in `composer.json`.

### 6. Configure Optional AI Integration

If AI-assisted features are implemented, configure the required API endpoint, model, and API key on the server.

Keep secrets outside public frontend code and exclude private environment files from Git.

The application should handle missing API configuration gracefully.

### 7. Run the Application

Open:

```text
http://localhost/TermTrail/
```

Register a new account and begin managing contracts.

---

## Security Considerations

TermTrail is intended to handle potentially sensitive contractual information. Important security practices include:

- Password hashing with PHP's `password_hash()` and verification with `password_verify()`.
- PDO prepared statements for database operations.
- Session-based authentication and access control.
- CSRF protection for state-changing requests.
- Output escaping and server-side input validation.
- Secure document upload validation and file storage.
- User-specific authorization for contracts, versions, obligations, and reports.
- Server-side storage of API credentials.
- Controlled logging and error handling.
- Explicit disclosure and consent before sending contract text to an external AI service.

Security controls should be verified through testing before production deployment.

---

## Current Limitations

- PDF extraction quality depends on the document's structure. Scanned PDFs may require OCR support.
- AI suggestions can be incomplete or incorrect and must be reviewed by the user.
- Automatic reminders may require additional scheduling configuration.
- External AI features depend on the configured provider and API availability.
- Contract analysis does not determine legal enforceability or replace professional legal advice.

---

## Future Enhancements

- OCR support for scanned agreements.
- Email and calendar integrations.
- Advanced full-text search.
- More detailed financial forecasting.
- Improved clause-change classification.
- Role-based collaboration and shared workspaces.
- Cloud deployment, automated backups, and monitoring.
- Expanded document privacy and retention controls.

