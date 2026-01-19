# Email Tracking MVP

A PHP-based email tracking application with webhook ingestion, thread management, and Monday.com synchronization capabilities.

## Features

- **Email Thread Management**: Track email conversations grouped by conversation ID
- **Webhook Ingestion**: Store raw webhook payloads for processing
- **Monday.com Sync**: Synchronize email threads with Monday.com (stubbed for now)
- **Idempotent Email Storage**: Prevent duplicate email records
- **Structured Logging**: JSON-formatted logs for monitoring and debugging

## Requirements

- PHP >= 8.1
- MySQL >= 5.7 or MariaDB >= 10.2
- Composer
- XAMPP (or any PHP/MySQL environment)

## Project Structure

```
networkemailtracking/
├── config/              # Configuration files
│   ├── database.php     # Database connection config
│   └── logging.php      # Logging setup
├── migrations/          # Database migration scripts
│   ├── 001_create_threads_table.php
│   ├── 002_create_emails_table.php
│   ├── 003_create_ingest_events_table.php
│   ├── 004_create_monday_sync_table.php
│   └── migrate.php      # Migration runner
├── public/              # Web-accessible directory
│   └── index.php        # Front controller
├── src/
│   ├── Core/            # Core framework classes
│   │   ├── Database.php
│   │   ├── Logger.php
│   │   └── Router.php
│   ├── Repositories/    # Data access layer
│   │   ├── ThreadRepository.php
│   │   ├── EmailRepository.php
│   │   ├── IngestEventRepository.php
│   │   └── MondaySyncRepository.php
│   └── Services/        # Business logic layer
│       ├── ThreadService.php
│       ├── EmailService.php
│       ├── IngestService.php
│       └── MondayService.php
├── logs/                # Application logs
├── vendor/              # Composer dependencies
├── .env                 # Environment variables (create from .env.example)
├── .env.example         # Example environment configuration
├── composer.json        # PHP dependencies
└── README.md           # This file
```

## Installation

### 1. Clone/Copy Project

Place the project in your XAMPP htdocs directory:
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/networkemailtracking
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

Copy the example environment file and configure:

```bash
cp .env.example .env
```

Edit `.env` with your database credentials:

```env
# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_HOST=localhost
DB_NAME=email_tracking
DB_USER=root
DB_PASS=your_password_here
DB_CHARSET=utf8mb4

# Session
SESSION_LIFETIME=3600

# Logging
LOG_LEVEL=debug
LOG_PATH=logs/app.log
```

### 4. Create Database

The migration script will create the database automatically, but you can also create it manually:

```sql
CREATE DATABASE email_tracking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Run Migrations

Execute the migration script to create all tables:

```bash
php migrations/migrate.php
```

You should see output like:
```
Checking database...
Database 'email_tracking' ready.

🔄 Running: 001_create_threads_table.php
✅ Completed: 001_create_threads_table.php
🔄 Running: 002_create_emails_table.php
✅ Completed: 002_create_emails_table.php
🔄 Running: 003_create_ingest_events_table.php
✅ Completed: 003_create_ingest_events_table.php
🔄 Running: 004_create_monday_sync_table.php
✅ Completed: 004_create_monday_sync_table.php

✨ Successfully executed 4 new migration(s)!
```

### 6. Start Server

#### Option A: Using PHP Built-in Server

```bash
cd public
php -S localhost:8000
```

Then visit: http://localhost:8000/health

#### Option B: Using XAMPP

Configure your Apache to point to the `public/` directory, or access via:
```
http://localhost/networkemailtracking/public/
```

## Database Schema

### Tables

#### `threads`
Stores email conversation threads grouped by `conversation_id`.

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| conversation_id | VARCHAR(255) | Unique conversation identifier |
| subject | VARCHAR(500) | Email subject |
| first_email_date | TIMESTAMP | Date of first email in thread |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Record update time |

#### `emails`
Stores individual email messages within threads.

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| thread_id | INT | Foreign key to threads |
| provider_message_id | VARCHAR(255) | Unique message ID from provider (Power Automate) |
| internet_message_id | VARCHAR(255) | Standard email Message-ID header |
| raw_json | TEXT | Raw email data as JSON |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Record update time |

**Unique constraint on `provider_message_id` ensures idempotency**

#### `ingest_events`
Stores raw webhook payloads before processing.

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| raw_json | TEXT | Raw webhook payload |
| webhook_secret_valid | BOOLEAN | Whether webhook secret was valid |
| processed | BOOLEAN | Whether event has been processed |
| created_at | TIMESTAMP | Record creation time |

#### `monday_sync`
Tracks synchronization status with Monday.com.

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| thread_id | INT | Foreign key to threads (unique) |
| monday_item_id | VARCHAR(255) | Monday.com item ID |
| sync_status | ENUM | Status: pending, synced, failed |
| synced_at | TIMESTAMP | Last sync time |
| error_message | TEXT | Error message if failed |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Record update time |

## API Endpoints

Currently, only a health check endpoint is implemented:

- `GET /health` - Check API status

**TODO**: Additional endpoints will be added for:
- `POST /api/webhook/ingest` - Webhook ingestion endpoint
- `POST /api/auth/login` - Authentication endpoint
- `GET /api/threads` - List threads
- `GET /api/emails` - List emails
- `POST /api/monday/sync` - Trigger Monday.com sync

## Development Status

This is the initial skeleton/scaffolding. The following components have **empty method bodies with TODO markers**:

### Repositories (all methods stubbed)
- `ThreadRepository` - Database operations for threads
- `EmailRepository` - Database operations for emails with idempotency
- `IngestEventRepository` - Database operations for webhook events
- `MondaySyncRepository` - Database operations for Monday.com sync tracking

### Services (all methods stubbed)
- `ThreadService` - Business logic for thread management
- `EmailService` - Business logic for email processing with idempotency
- `IngestService` - Webhook ingestion and processing logic
- `MondayService` - Monday.com synchronization logic (stubbed)

### Not Yet Implemented
- **Webhook endpoint**: Secret validation and payload storage
- **Authentication**: Login system (will be stubbed initially)
- **Power Automate parsing**: Extract conversation/message IDs from JSON
- **Monday.com API integration**: Replace stubs with real API calls
- **Web UI**: Dashboard for viewing threads and sync status

## Next Steps

1. **Implement Repository Methods**: Fill in the TODO methods in repository classes
2. **Implement Service Methods**: Add business logic to service classes
3. **Add Webhook Endpoint**: Create `/api/webhook/ingest` with secret validation
4. **Add Authentication**: Implement basic session-based auth
5. **Build UI**: Create simple dashboard with Tailwind CSS
6. **Monday.com Integration**: Replace stubs with real API calls

## Environment Variables Reference

| Variable | Description | Default |
|----------|-------------|---------|
| APP_ENV | Application environment | development |
| APP_DEBUG | Enable debug mode | true |
| APP_URL | Application URL | http://localhost |
| DB_HOST | MySQL host | localhost |
| DB_NAME | Database name | email_tracking |
| DB_USER | Database user | root |
| DB_PASS | Database password | (empty) |
| DB_CHARSET | Database charset | utf8mb4 |
| SESSION_LIFETIME | Session lifetime in seconds | 3600 |
| LOG_LEVEL | Logging level (debug, info, warning, error) | debug |
| LOG_PATH | Log file path | logs/app.log |

### Future Environment Variables (not yet used)
- `WEBHOOK_SHARED_SECRET` - Secret for validating webhook requests
- `ADMIN_USERNAME` - Admin user for authentication
- `ADMIN_PASSWORD_HASH` - Hashed admin password
- `MONDAY_API_KEY` - Monday.com API key

## Logging

Application logs are stored in JSON format in the `logs/` directory. The log file rotates daily and keeps 14 days of history.

View logs:
```bash
tail -f logs/app.log
```

## Testing

Test the database connection:
```bash
php -r "require 'vendor/autoload.php'; \$env = Dotenv\Dotenv::createImmutable(__DIR__); \$env->load(); \$db = App\Core\Database::getInstance(); echo 'Connected to: ' . \$db->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;"
```

Test the health endpoint:
```bash
curl http://localhost:8000/health
```

Expected response:
```json
{
  "status": "ok",
  "message": "Email Tracking API is running",
  "timestamp": "2026-01-16T12:00:00+00:00"
}
```

## License

Proprietary - Internal use only

## Support

For questions or issues, contact the development team.
