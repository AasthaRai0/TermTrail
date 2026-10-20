# TermTrail AI
### Understand the contract. Manage every commitment.

**An AI-powered contract intelligence and lifecycle management platform built with PHP, MySQL, and Gemini AI.**

TermTrail AI helps users understand complex contracts, review potentially risky clauses, extract important dates and financial commitments, and manage obligations, payments, renewals, and deadlines through a centralized dashboard.

Instead of treating a contract as a static PDF, TermTrail AI turns it into structured, searchable information that users can review and manage throughout its lifecycle.

> **Disclaimer:** TermTrail AI provides informational assistance, not legal advice. AI-generated summaries, classifications, and risk findings require human review.

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [AI and Document Intelligence](#ai-and-document-intelligence)
- [Technology Stack](#technology-stack)
- [System Architecture](#system-architecture)
- [Project Structure](#project-structure)
- [Database Design](#database-design)
- [Getting Started](#getting-started)
- [Environment Configuration](#environment-configuration)
- [How It Works](#how-it-works)
- [Security](#security)
- [Testing](#testing)
- [Limitations](#limitations)
- [Future Enhancements](#future-enhancements)

---

## Overview

Contracts often contain complicated legal language, financial conditions, renewal clauses, deadlines, and obligations that are difficult to track manually.

TermTrail AI is designed to simplify this process through a combination of document processing, AI-assisted analysis, evidence-grounded question answering, and contract lifecycle management.

### The platform helps users:

- Understand lengthy agreements through plain-English summaries.
- Identify clauses that may require additional attention.
- Review AI-generated risk findings with supporting document evidence.
- Extract dates, amounts, parties, and contractual commitments.
- Track approved obligations, payments, renewals, and deadlines.
- Ask questions about a contract using its extracted content.
- Compare contract versions and review textual changes.
- Generate reports and maintain an audit trail.

### Project Goals

- Build a complete full-stack application using PHP and MySQL.
- Integrate an external LLM API without requiring a separate AI backend.
- Keep contract records and extracted document content organized.
- Make AI results explainable and subject to human approval.
- Provide a practical contract management workflow beyond basic document summarization.

---

## Key Features

### 1. Authentication and Role-Based Access

- User registration, login, and logout.
- Secure password hashing.
- Session-based authentication.
- Role-based access for Admin, Manager, and Viewer.
- Protected pages and API endpoints.
- User settings and password management.

### 2. Contract Dashboard

The dashboard provides an overview of contract activity through database-driven metrics.

- Total and active contracts.
- Contracts approaching expiry.
- Expired contracts.
- Contracts awaiting review.
- Open and overdue obligations.
- Upcoming and overdue payments.
- High-risk contract indicators.
- Recent activity and upcoming deadlines.
- Interactive charts and contract summaries.

### 3. Contract Library

Manage contracts in one centralized workspace.

- Upload PDF, DOCX, and TXT documents.
- Create contracts manually.
- Edit contract metadata.
- Search, sort, filter, and paginate contracts.
- Organize contracts by type, counterparty, status, and risk level.
- Add review notes and tags.
- Archive and restore records.
- Access authorized original documents.

### 4. Counterparty Management

Maintain information about organizations and individuals associated with contracts.

- Contact details and organization information.
- Counterparty notes.
- Related contract records.
- Search, edit, and archive capabilities.
- Connections to relevant obligations and payments.

### 5. AI Contract Summarization

Transform extracted contract text into a more readable explanation.

- Executive summary.
- Plain-English contract explanation.
- Parties and agreement purpose.
- Important dates and financial terms.
- Key responsibilities.
- Termination and renewal conditions.
- Important limitations and commitments.

### 6. Clause Classification

Identify and organize relevant contract clauses, including:

- Payment terms.
- Termination conditions.
- Confidentiality.
- Intellectual property.
- Indemnification.
- Limitation of liability.
- Dispute resolution.
- Governing law.
- Automatic renewal.
- Data protection.
- Penalties and late fees.
- Force majeure.
- Service-level commitments.

Each supported finding can be associated with a source quotation for review.

### 7. Risk Findings and Review Workflow

Highlight potentially unfavorable, ambiguous, missing, or unusual contractual terms.

- Low, Medium, and High severity levels.
- Explanations for flagged clauses.
- Supporting document excerpts.
- Suggested follow-up actions.
- An explainable heuristic risk score.
- Review status and reviewer notes.
- Approval and rejection workflow for AI suggestions.

AI-generated findings are recommendations for further review, not definitive legal conclusions.

### 8. Obligation Management

Convert reviewed contractual commitments into trackable tasks.

- Obligation title and description.
- Linked contract and source clause.
- Responsible party and internal owner.
- Due date and priority.
- Status tracking.
- Completion notes.
- Manual obligation creation.
- Approval of AI-generated obligation suggestions.

### 9. Payments and Renewals

Track contractual financial commitments and important renewal dates.

- Payment amounts and currencies.
- Payment due dates and statuses.
- Paid, planned, overdue, and cancelled records.
- Contract expiry tracking.
- Renewal and notice deadlines.
- Upcoming financial commitments.

TermTrail AI tracks contractual payments; it does not process transactions or store payment card information.

### 10. Deadline Calendar and Notifications

- Calendar view for obligations and deadlines.
- Upcoming expiry and renewal reminders.
- Payment due-date notifications.
- Overdue obligation alerts.
- In-app notification center.
- Optional email notifications through SMTP.
- Scheduled reminders through a PHP CLI script and Windows Task Scheduler.

Automatic reminders require the scheduler and relevant configuration to be enabled.

### 11. Evidence-Grounded Contract Copilot

Ask questions about an individual contract in a conversational interface.

Example questions:

- When does this contract expire?
- What is the termination notice period?
- Which payment obligations are specified?
- Does the agreement include automatic renewal?
- What happens if a party terminates early?
- Which clauses may require further review?

The retrieval workflow can use stored document chunks, optional embeddings, cosine similarity in PHP, and MySQL FULLTEXT or keyword search as a fallback.

Answers should reference retrieved document passages. When relevant evidence cannot be found, the Copilot should clearly communicate that limitation rather than inventing an answer.

### 12. Contract Version Comparison

- Upload new versions of existing contracts.
- Maintain version history.
- Compare extracted contract text.
- Highlight added and removed text.
- Review potentially significant changes.
- Store comparison records for later reference.

The quality of a comparison depends on successful text extraction from both versions.

### 13. Clause Library

- Store reusable clause references.
- Organize clauses by category.
- Search and filter templates.
- Save explanations, notes, and tags.
- Manage templates according to user permissions.

Templates are reference material and may require professional review before use.

### 14. Reports and Exports

Generate reports for:

- Contract summaries.
- Risk findings.
- Upcoming expirations.
- Obligations and deadlines.
- Payments.
- Counterparties.
- Activity history.

Supported export formats include CSV and PDF, depending on the configured implementation.

### 15. Audit Logs

Maintain a history of important application events.

- Contract creation and modification.
- Document uploads.
- Analysis activity.
- Risk review decisions.
- Obligation approvals.
- Payment status changes.
- Version comparisons.
- Report exports.
- Administrative changes.

Access to audit records is restricted according to application permissions.

---

## AI and Document Intelligence

TermTrail AI uses a PHP-based document processing pipeline and an external AI API.

### Processing Pipeline

```text
Contract Upload
      |
      v
File Validation and Secure Storage
      |
      v
Text Extraction
(PDF / DOCX / TXT)
      |
      v
Text Cleaning and Chunking
      |
      v
AI Analysis through PHP cURL
      |
      v
Structured JSON Validation
      |
      v
Evidence Verification
      |
      v
MySQL Persistence
      |
      v
Human Review and Approval
      |
      v
Obligations, Deadlines and Reports
```

### Retrieval-Augmented Generation (RAG)

The Copilot uses a retrieval workflow to ground answers in relevant contract content.

1. Extracted contract text is divided into manageable chunks.
2. Chunks and their source metadata are stored in MySQL.
3. Optional embeddings can be generated through a configured API.
4. Relevant chunks are retrieved using semantic similarity or keyword search.
5. The LLM receives the question and selected passages.
6. The response is presented with supporting excerpts where available.

Embeddings are optional. Keyword retrieval can provide a fallback when embedding credentials or services are unavailable.

### AI Reliability Principles

- Validate the structure of AI responses before storing them.
- Verify evidence quotations against extracted text.
- Preserve uncertainty instead of inventing missing information.
- Keep AI suggestions separate from approved commitments.
- Handle invalid responses, timeouts, and API errors.
- Never claim that an analysis succeeded when processing failed.

---

## Technology Stack

| Layer | Technologies |
|---|---|
| Frontend | HTML5, CSS3, Bootstrap 5, Vanilla JavaScript |
| Icons | Lucide icons |
| Charts | Chart.js |
| Calendar | FullCalendar.js |
| Backend | PHP 8.2+, PHP cURL |
| Database | MySQL / MariaDB |
| Database access | PDO |
| AI | Gemini API or a configured compatible LLM API |
| PDF extraction | `smalot/pdfparser` |
| DOCX extraction | `phpoffice/phpword` |
| PDF generation | `dompdf/dompdf` |
| Email | `phpmailer/phpmailer` |
| Text comparison | `jfcherng/php-diff` |
| Dependency management | Composer |
| Local environment | XAMPP for Windows |
| Testing | PHPUnit |

**Architecture decision:** The application uses PHP for its backend and AI integration. It does not require Python, FastAPI, Node.js, React, or a separate frontend server.

---

## System Architecture

```text
+--------------------------------------+
|          Browser / Frontend          |
| HTML, CSS, Bootstrap, Vanilla JS     |
+------------------+-------------------+
                   |
                   | HTTP / Fetch API
                   v
+--------------------------------------+
|          PHP Application             |
| Routing, Authentication, Validation  |
| Controllers, Models, Services        |
+-----------+------------------+-------+
            |                  |
            v                  v
+-------------------+  +----------------------+
| MySQL / MariaDB   |  | External AI API      |
| Contracts, Users, |  | Summaries, Clauses,  |
| Risks, Tasks,     |  | Risk Suggestions,    |
| Payments, Chunks  |  | Evidence-grounded QA |
+-------------------+  +----------------------+
            |
            v
+--------------------------------------+
| Protected File Storage               |
| Original Documents, Extracted Text,  |
| Generated Reports                    |
+--------------------------------------+
```

---

## Project Structure

The following is the intended structure. Actual filenames may vary slightly with the implementation.

```text
termtrail/
├── index.php
├── .htaccess
├── .env.example
├── .gitignore
├── composer.json
├── README.md
│
├── frontend/
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   ├── pages/
│   └── components/
│
├── backend/
│   ├── config/
│   ├── middleware/
│   ├── controllers/
│   ├── models/
│   ├── services/
│   ├── helpers/
│   └── api/
│
├── database/
│   ├── schema.sql
│   └── seed.sql
│
├── storage/
│   ├── contracts/
│   ├── extracted_text/
│   ├── reports/
│   └── logs/
│
├── cron/
│   └── reminders.php
│
├── tests/
└── vendor/
```

The `vendor/` directory is created by Composer. Storage directories must be created and configured before file uploads and report generation are used.

---

## Database Design

The database is designed to organize contracts, analysis results, lifecycle activities, and application users.

Core tables include:

| Table | Purpose |
|---|---|
| `users` | User accounts and roles |
| `counterparties` | Organizations and individuals |
| `contracts` | Contract metadata and status |
| `contract_versions` | Uploaded versions and version history |
| `analysis_results` | AI analysis output and summaries |
| `clauses` | Extracted contract clauses |
| `risk_findings` | Risk findings and review status |
| `document_chunks` | Extracted text chunks and optional embeddings |
| `obligation_suggestions` | AI-generated obligation proposals |
| `obligations` | Confirmed obligations and deadlines |
| `payments` | Contractual payment records |
| `reminders` | Scheduled reminder records |
| `notifications` | In-app notification data |
| `review_notes` | Contract review comments |
| `chat_messages` | Copilot conversation history |
| `activity_logs` | Audit trail |
| `version_comparisons` | Comparison records |
| `clause_templates` | Reusable clause references |

Additional tables may be used for processing jobs, application settings, or other supporting features.

The schema should use foreign keys, indexes, appropriate data types, and transactions where necessary. Uploaded documents are stored as files rather than large binary objects in MySQL.

---

## Getting Started

### Prerequisites

Install the following:

- XAMPP with PHP 8.2 or a compatible PHP 8.x version.
- MySQL or MariaDB.
- Composer.
- A modern web browser.
- An optional Gemini API key for AI functionality.

### Step 1: Place the project in XAMPP

Copy or clone the project into:

```text
C:\xampp\htdocs\termtrail
```

Start **Apache** and **MySQL** from the XAMPP Control Panel.

### Step 2: Install PHP dependencies

Open PowerShell in the project directory:

```powershell
cd C:\xampp\htdocs\termtrail
composer install
```

Ensure Composer uses the same PHP installation as XAMPP. If the command is unavailable, install Composer and configure its PHP executable path.

### Step 3: Configure PHP extensions

Open the `php.ini` used by XAMPP and check that the required extensions are enabled, including:

- `curl`
- `fileinfo`
- `mbstring`
- `openssl`
- `pdo_mysql`
- `zip`

Enable other extensions required by the installed libraries.

Restart Apache after changing `php.ini`.

### Step 4: Create the database

Open:

`http://localhost/phpmyadmin/`

Create a database named:

```sql
CREATE DATABASE termtrail_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Select `termtrail_db` and import:

```text
database/schema.sql
```

To load fictional demonstration data, optionally import:

```text
database/seed.sql
```

Review the seed file and demo credentials before using them. Do not import seed data repeatedly into an existing database unless the seed process is designed to be repeatable.

### Step 5: Configure environment variables

Copy `.env.example` to `.env` and configure your local values.

Example:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=termtrail_db
DB_USER=root
DB_PASS=

APP_URL=http://localhost/termtrail
APP_ENV=development

GEMINI_API_KEY=
GEMINI_MODEL=

EMBEDDING_API_KEY=

SMTP_HOST=
SMTP_PORT=587
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_ENCRYPTION=tls
```

These are example settings, not guaranteed credentials for every installation. Use the database username and password configured on your machine.

Add `.env` to `.gitignore`. Never commit actual API keys, database passwords, SMTP credentials, or private documents.

### Step 6: Prepare file storage

Create the required directories if they do not already exist:

```text
storage/contracts/
storage/extracted_text/
storage/reports/
storage/logs/
```

Ensure the PHP process can write to the necessary directories.

Private contracts must not be publicly downloadable through guessed URLs. Use protected storage and authorized PHP download routes. If storage resides under the project directory, configure access restrictions appropriately.

### Step 7: Launch the application

Open:

**http://localhost/termtrail/**

Register an account or use the demo account documented in the seed data.

### Step 8: Configure optional AI analysis

Add a valid AI API key and supported model identifier to `.env`.

AI features require internet connectivity and a valid provider configuration. If no key is configured, supported manual workflows and fallback functionality should remain available.

### Step 9: Configure scheduled reminders

For automated reminders, configure Windows Task Scheduler to execute the PHP CLI script:

```text
C:\xampp\php\php.exe
```

with the script argument:

```text
C:\xampp\htdocs\termtrail\cron\reminders.php
```

Set a suitable schedule and test the script manually before relying on automatic reminders. Configure SMTP separately if email notifications are needed.

---

## How It Works

### Contract Analysis Workflow

1. The user uploads a supported contract.
2. PHP validates the file and saves it in protected storage.
3. A document parser extracts available text.
4. The text is cleaned and divided into chunks.
5. The configured AI provider generates structured analysis.
6. PHP validates the response and supporting evidence.
7. Analysis results are saved in MySQL.
8. The user reviews summaries, clauses, and risk findings.
9. Approved obligation suggestions become trackable commitments.
10. The user manages deadlines, payments, renewals, and reports.

### Copilot Workflow

1. The user asks a question within a contract.
2. The application retrieves relevant stored text chunks.
3. Optional embeddings improve semantic retrieval.
4. Relevant passages are supplied to the LLM.
5. The response is displayed with available source evidence.
6. The user can inspect the referenced text to verify the answer.

### Lifecycle Workflow

```text
Upload
  |
  v
Extract and Analyze
  |
  v
Review Findings
  |
  v
Approve Commitments
  |
  v
Track Obligations and Payments
  |
  v
Monitor Deadlines and Renewals
  |
  v
Compare Versions and Generate Reports
```

---

## Security

Security is an essential part of the application.

TermTrail AI should implement:

- Prepared statements through PDO.
- Password hashing and verification.
- Session-based authentication.
- CSRF protection for state-changing requests.
- Server-side input validation.
- HTML output escaping.
- File type, size, and upload validation.
- Randomized stored filenames.
- Protected document downloads.
- Role and record ownership checks.
- Environment-based secret management.
- Restricted access to private files and configuration.
- Safe error handling and application logging.
- Appropriate authorization for reports and audit logs.

Uploaded documents and AI responses must be treated as untrusted input.

For production deployment, use HTTPS, production-grade database credentials, secure server configuration, and an appropriate backup and recovery strategy.

---

## Testing

Run the test suite using the configured PHPUnit installation:

```powershell
vendor\bin\phpunit
```

The test configuration may require a separate test database and environment variables.

Recommended test areas:

- Authentication and session behavior.
- Role and ownership enforcement.
- Contract CRUD operations.
- Upload validation.
- AI response validation.
- Evidence verification.
- Risk score calculation.
- Obligation approval.
- Date and amount extraction.
- Version comparison.
- CSV formula escaping.
- Unauthorized document access.

Test against fictional records and a dedicated test database rather than production or personal data.

---

## Limitations

- AI analysis depends on the configured provider, model, API quota, and network availability.
- Scanned PDFs and image-based contracts require OCR, which is outside the default text-extraction workflow.
- Extracted text may lose some original formatting or layout.
- Complex tables, handwritten text, and unusual document structures may not extract reliably.
- Risk scores are heuristic indicators, not legally validated assessments.
- Copilot accuracy depends on extraction quality and retrieval relevance.
- Embedding-based retrieval requires a compatible embedding provider; keyword search is the fallback.
- Email notifications require SMTP configuration.
- Automatic reminders require Windows Task Scheduler configuration.
- Local XAMPP configuration is suitable for development and demonstration, not automatically production-ready.

---

## Future Enhancements

Potential future improvements include:

- OCR for scanned contracts.
- Multilingual contract summarization.
- Improved semantic retrieval and reranking.
- Advanced contract comparison with clause-level change detection.
- Configurable approval workflows.
- More granular team permissions.
- Secure cloud storage integration.
- Calendar provider integration.
- Advanced reporting and analytics.
- Additional document formats.
- Improved evaluation of AI extraction accuracy.
- Automated backup and restore workflows.

---