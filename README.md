# TermTrail AI
### Understand the Contract. Manage Every Commitment.

**TermTrail AI** is an AI-powered contract intelligence and lifecycle management platform designed to help users understand complex legal documents, identify potentially risky clauses, track contractual obligations, and manage important deadlines from a single workspace.

Instead of treating contracts as static documents, TermTrail AI transforms them into actionable, searchable, and manageable information.

> Turn complex contracts into clear insights, actionable commitments, and informed decisions.

---

## Overview

Contracts often contain complicated legal language, hidden conditions, payment commitments, renewal clauses, and strict deadlines. Manually reviewing and managing these details can be time-consuming and error-prone.

TermTrail AI combines document intelligence with contract lifecycle management to help users:

- Understand contracts through plain-English summaries.
- Identify potentially concerning clauses with supporting evidence.
- Extract obligations, payment terms, and important deadlines.
- Monitor renewals, termination dates, and pending commitments.
- Compare contract versions and review document changes.
- Ask questions about contracts using evidence-grounded AI assistance.
- Manage the contract review process through a centralized dashboard.

The platform is designed to support contract understanding and organization, not replace qualified legal advice.

## Key Features

### 1. Contract Intelligence

Upload contracts and transform document content into structured information.

- PDF, DOCX, and TXT document ingestion.
- Document text extraction and processing.
- Plain-English contract summaries.
- Clause classification and categorization.
- Financial, termination, privacy, and intellectual-property risk review.
- Evidence-backed risk findings with relevant text excerpts.
- Suggested obligations, deadlines, renewal terms, and payment conditions.

### 2. Contract Lifecycle Management

Manage contracts throughout their lifecycle from one place.

- Centralized contract library.
- Contract metadata and counterparty management.
- Contract status tracking.
- Start dates, expiration dates, and renewal dates.
- Search, filtering, sorting, and archiving.
- Contract-specific notes and activity history.

### 3. Obligation & Deadline Tracking

Turn contractual commitments into actionable tasks.

- Create and manage contractual obligations.
- Track due dates, priorities, and completion status.
- Review and approve AI-suggested obligations.
- Monitor upcoming and overdue commitments.
- Track renewal and termination notice deadlines.
- Manage payment amounts, due dates, and payment status.
- Receive in-app reminders and notifications.

### 4. Contract Risk Review

Review potentially important contractual terms through a structured interface.

| Risk category | What it helps review |
|---|---|
| Financial | Fees, payment conditions, penalties, and cost-related terms |
| Termination | Termination conditions, notice periods, and renewal provisions |
| Privacy | Data handling, confidentiality, and privacy-related obligations |
| Intellectual Property | Ownership, licensing, and intellectual-property clauses |

Risk labels are intended as review indicators, not definitive legal judgments. Findings should include supporting document evidence whenever available.

### 5. Contract Version Comparison

Understand how a contract changes over time.

- Store multiple contract versions.
- Compare extracted text between versions.
- Highlight added, removed, and modified passages.
- Review potentially significant changes.
- Preserve comparison history.

### 6. Evidence-Grounded AI Copilot

Interact with contract documents through a contextual AI assistant.

- Ask questions about a selected contract.
- Retrieve relevant passages from the document.
- Generate answers grounded in retrieved evidence.
- Display supporting excerpts or source references.
- Maintain conversation history.
- Indicate when sufficient evidence is unavailable.

The copilot should not fabricate quotations or claim that a contract contains information that could not be found.

### 7. Dashboard & Analytics

Get an overview of contract activity and upcoming responsibilities.

- Total and active contracts.
- Expiring and expired contracts.
- High-priority risk findings.
- Pending and overdue obligations.
- Upcoming payments and renewals.
- Contract status visualizations.
- Risk distribution charts.
- Recent activity and quick actions.

Dashboard metrics should reflect actual database records.

### 8. Reports & Exports

Generate useful outputs for contract review and recordkeeping.

- Downloadable contract review reports in PDF format.
- Risk findings and supporting evidence.
- Contract summaries and key dates.
- Obligation and payment information.
- Review notes and checklist progress.
- CSV exports for supported tabular data.

### 9. Authentication & Security

- Secure registration and login.
- Password hashing.
- Session-based authentication.
- User-specific contract access.
- Prepared database statements.
- Server-side validation.
- CSRF protection.
- Secure document upload and access controls.
- Environment-based secret management.

---

## Technology Stack

| Layer | Technologies |
|---|---|
| Frontend | HTML5, CSS3, Bootstrap 5, Vanilla JavaScript |
| Backend | PHP 8.2+ |
| Database | MySQL, phpMyAdmin |
| Database access | PHP PDO |
| Data visualization | Chart.js |
| PDF reports | Dompdf |
| AI service | Python, FastAPI |
| Document processing | PDF/DOCX text extraction and text preprocessing |
| Retrieval-augmented generation | Embeddings, retrieval, optional ChromaDB |
| Local LLM option | Ollama |
| Development environment | XAMPP, Composer, Python virtual environment |
| Version control | Git and GitHub |

**Architecture decision:** PHP and MySQL handle the core application, authentication, contract management, obligations, payments, and reporting. The optional Python service provides AI-specific capabilities. Core management features should remain usable when the AI service is unavailable.

---

## System Architecture

```text
                    USER
                      |
                      v
          HTML + CSS + JavaScript
                      |
                      v
             PHP Application
              /           \
             v             v
       MySQL Database   Document Storage
             |
             v
      Contract Metadata,
      Obligations, Risks,
      Payments & Activity

             PHP Application
                      |
                      v
              Python FastAPI
                      |
             Document Processing
                      |
           Text Extraction / NLP
                      |
             Embedding & Retrieval
                      |
          Optional LLM / Ollama
                      |
                      v
         Summaries, Risk Insights,
         Suggested Obligations
         & Evidence-Based Answers
```

*The diagram represents the intended architecture. Actual capabilities depend on the implemented code and configured services.*

---

## Application Workflow

1. **Register or sign in** to access a personal workspace.
2. **Upload or create a contract** and provide relevant metadata.
3. **Extract document content** using the supported document-processing pipeline.
4. **Review AI-generated insights**, including summaries, clause classifications, and potential risks.
5. **Verify suggested obligations** before adding them to the obligation tracker.
6. **Monitor deadlines and payments** through the dashboard and calendar.
7. **Compare versions** when an updated contract becomes available.
8. **Ask questions and review evidence** using the contract copilot.
9. **Export reports** for recordkeeping and further review.

---

## Project Structure

```text
TermTrail/
├── index.php
├── login.php
├── register.php
├── dashboard.php
├── contracts.php
├── contract-details.php
├── obligations.php
├── payments.php
├── calendar.php
├── copilot.php
├── reports.php
├── settings.php
│
├── config/
│   ├── database.php
│   └── app.php
│
├── includes/
│   ├── header.php
│   ├── sidebar.php
│   ├── footer.php
│   └── functions.php
│
├── modules/
│   ├── auth/
│   ├── contracts/
│   ├── analysis/
│   ├── obligations/
│   ├── payments/
│   ├── reminders/
│   ├── comparison/
│   ├── copilot/
│   └── reports/
│
├── api/
├── services/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── database/
│   └── schema.sql
│
├── storage/
│   └── .gitkeep
│
├── python_ai/
│   ├── main.py
│   ├── requirements.txt
│   └── .env.example
│
├── tests/
├── .env.example
├── .gitignore
├── composer.json
└── README.md
```

*The exact structure may differ slightly depending on the implementation. Keep this documentation aligned with the files actually generated.*

---

## Installation & Setup

### Prerequisites

Install the following:

- [XAMPP](https://www.apachefriends.org/) with PHP and MySQL.
- [Composer](https://getcomposer.org/).
- [Python](https://www.python.org/) 3.11 or newer if using the AI service.
- A modern web browser.
- An optional LLM provider or local Ollama installation for AI features that require a language model.

### 1. Clone the repository

```bash
git clone https://github.com/YOUR_USERNAME/TermTrail.git
```

Replace `YOUR_USERNAME` with your GitHub username.

Move the project folder into:

```text
C:\xampp\htdocs\TermTrail
```

### 2. Start XAMPP

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 3. Create the database

Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Create a database named:

```text
termtrail_db
```

Import the SQL schema from the project’s `database/schema.sql` file.

If the SQL script already creates the database, follow its instructions instead of creating it twice.

### 4. Configure environment variables

Copy `.env.example` to `.env` and configure the database connection and any optional AI provider settings supported by the implementation.

Example configuration:

```env
APP_NAME=TermTrail AI
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/TermTrail

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=termtrail_db
DB_USERNAME=root
DB_PASSWORD=

AI_SERVICE_URL=http://127.0.0.1:8000
LLM_API_KEY=
```

Use the database credentials configured on your machine. A blank MySQL password is appropriate only if your local development installation is configured that way.

Never commit `.env` or real API keys to GitHub.

### 5. Install PHP dependencies

Open a terminal in the project directory:

```powershell
cd C:\xampp\htdocs\TermTrail
composer install
```

If the project uses Dompdf, ensure it is included in `composer.json` and installed successfully.

### 6. Open the application

Visit:

```text
http://localhost/TermTrail/
```

Register an account and begin adding contracts.

### 7. Configure the optional Python AI service

If the generated project includes `python_ai/requirements.txt`, open a separate terminal:

```powershell
cd C:\xampp\htdocs\TermTrail\python_ai

py -3.11 -m venv .venv

.\.venv\Scripts\Activate.ps1

python -m pip install --upgrade pip

pip install -r requirements.txt
```

Start the service using the entry point actually provided by the project. For a FastAPI application exposing `app` in `main.py`, the command is:

```powershell
uvicorn main:app --reload --host 127.0.0.1 --port 8000
```

If PowerShell blocks virtual-environment activation, use the appropriate local execution-policy adjustment or run the virtual environment's Python executable directly.

The AI service and model provider must be configured before their dependent features can work. Contract management should continue to operate without them.

---

## Security & Responsible AI

TermTrail AI is intended to assist with document understanding and contract organization.

- AI-generated summaries and findings may be incomplete or incorrect.
- Risk classifications are indicators for human review, not legal conclusions.
- Always verify important clauses, obligations, and dates against the original document.
- Do not treat extracted deadlines as confirmed until they have been reviewed.
- Avoid uploading confidential documents to third-party AI providers without appropriate authorization.
- Protect uploaded documents and credentials.
- Seek advice from a qualified legal professional for legal interpretation or decisions.

---

## Testing

Verify the implemented application using the following checks:

- Registration, login, and logout.
- Access control between different user accounts.
- Contract creation, upload, editing, and archiving.
- PDF, DOCX, and TXT text extraction.
- Handling of invalid and oversized uploads.
- AI analysis success and failure cases.
- Risk findings and supporting evidence.
- Obligation approval, updates, and completion.
- Payment and renewal tracking.
- Contract version comparison.
- PDF report generation and CSV export.
- Database validation and error handling.
- Responsive layout and navigation.

Only mark tests as passed after they have actually been executed.

---

## Current Development Status

TermTrail AI is designed as a full-stack project combining contract lifecycle management with AI-powered document intelligence.

Implementation status should be updated according to the actual repository. Do not label planned or partially implemented features as production-ready.

---

## Future Enhancements

- OCR support for scanned contracts.
- Improved semantic contract-version comparison.
- Advanced hybrid retrieval and reranking for contract question answering.
- Configurable team workspaces and role-based permissions.
- Email and calendar integrations.
- Customizable contract review policies.
- More advanced audit trails and compliance reporting.
- Multilingual document understanding.

---

## Why TermTrail AI?

TermTrail AI brings document understanding and ongoing contract management together. Rather than stopping at a generated summary, it helps users follow contractual commitments, review potentially important terms, and keep track of deadlines throughout a contract's lifecycle.

**Built to make complex contracts easier to understand and commitments easier to manage.**

---