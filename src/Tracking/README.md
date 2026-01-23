# Email Open Tracking Module

This module provides email open tracking functionality using disguised 1x1 pixel image beacons.

## Overview

The tracking system automatically injects invisible tracking pixels into outbound emails and records when recipients open them. It intelligently filters out:
- Internal BCC opens (networking@veerless.com)
- Bot/scanner opens (email security tools)
- Opens within 30 seconds of sending (likely automated)

## Architecture

```
src/Tracking/
├── Controllers/
│   └── ImageController.php      # Serves tracking beacon, records opens
├── Services/
│   └── TrackingService.php      # Beacon generation, extraction, bot detection
├── Repositories/
│   └── TrackingRepository.php   # Database operations
└── Contracts/
    └── TrackingRepositoryInterface.php
```

## How It Works

### 1. Draft Creation (OutlookDraftService)
- Generates unique 32-character beacon ID
- Injects disguised image: `<img src="{APP_URL}/public/img/spacer.gif?cache={beacon_id}" width="1" height="1" alt="" />`
- Creates tracking record with status='draft'

### 2. Email Sent (WebhookService)
- Power Automate webhook fires when email confirmed sent
- Extracts beacon ID from email body using regex
- Activates tracking record: status='draft' → 'active', sets activated_at timestamp

### 3. Email Opened (ImageController)
- Recipient opens email, client requests beacon image
- Calculates time elapsed since activation
- Checks if bot via timing (<30s) or user-agent patterns
- Records open event with metadata
- Updates counters: total_opens++, recipient_opens++ (if not bot and not first open)
- Returns 1x1 transparent GIF

### 4. Dashboard Display (DashboardController)
- Joins tracking data with emails
- Shows open status, open count, timestamps
- Calculates open rate statistics

## Database Schema

### email_tracking
- `beacon_id` (VARCHAR 64 UNIQUE) - Tracking identifier
- `email_id` (BIGINT) - Links to emails table
- `status` (ENUM) - 'draft' or 'active'
- `activated_at` (DATETIME) - When email was sent
- `total_opens` (INT) - All opens including BCC/bots
- `recipient_opens` (INT) - Meaningful opens only
- `first_opened_at`, `last_opened_at` (DATETIME)

### open_events
- `beacon_id` (VARCHAR 64) - References email_tracking
- `opened_at` (DATETIME) - Event timestamp
- `seconds_since_activation` (INT) - Timing for bot detection
- `user_agent` (TEXT) - Browser/client info
- `ip_address` (VARCHAR 45) - Opener IP
- `is_bot` (BOOLEAN) - Bot flag
- `counted_as_recipient_open` (BOOLEAN) - Whether counted

## Key Logic

### First Open = BCC (Not Counted)
The first open is always assumed to be from networking@veerless.com BCC mailbox. This open increments `total_opens` but NOT `recipient_opens`.

### Bot Detection
An open is flagged as bot if:
1. Timing: < 30 seconds after activation (configurable via `TRACKING_BOT_DELAY_SECONDS`)
2. User-Agent: Matches known scanner patterns (Mimecast, Proofpoint, Barracuda, etc.)

Bot opens are logged but don't increment `recipient_opens`.

### Recipient Opens
`recipient_opens = total_opens - 1 (BCC) - bot_opens`

## Configuration

### Environment Variables (.env)
```
APP_URL=https://yourdomain.com          # Required for absolute beacon URLs
TRACKING_BOT_DELAY_SECONDS=30           # Bot detection threshold
```

## Routes

### GET /public/img/spacer.gif?cache={beacon_id}
- Serves tracking beacon
- Records open event
- Returns 1x1 transparent GIF with no-cache headers

## Integration Points

### Modified Files
1. **src/Services/OutlookDraftService.php**
   - Injects tracking beacon in email body
   - Creates draft tracking record

2. **src/Services/WebhookService.php**
   - Activates tracking when email confirmed sent
   - Extracts beacon ID from webhook body

3. **src/Controllers/DashboardController.php**
   - Joins tracking data with emails
   - Displays open metrics and statistics

4. **index.php**
   - Registers beacon route
   - Wires up dependencies

## Bot User-Agent Patterns

Hardcoded patterns in `TrackingService.php`:
- Mimecast
- Proofpoint
- Barracuda
- Office.*Existence
- Link.*Check
- Security.*Scan
- Mail.*Security
- ZoomInfo
- Email.*Security
- Virus.*Scan
- Protection.*Service

## Performance Considerations

- Beacon endpoint must respond <100ms for email client compatibility
- Returns GIF immediately, records event synchronously
- Future enhancement: Async event recording with message queue

## Security & Privacy

- Beacon IDs are cryptographically secure (16 random bytes → 32 hex)
- Never exposes email IDs directly
- IP addresses stored (may be PII - consider GDPR/CCPA)
- No cross-site tracking - scoped to email system only

## Removal Instructions

To remove this module completely:

1. **Drop tables:**
   ```sql
   DROP TABLE IF EXISTS open_events;
   DROP TABLE IF EXISTS email_tracking;
   ```

2. **Delete tracking folder:**
   ```bash
   rm -rf src/Tracking/
   ```

3. **Remove migration:**
   ```bash
   rm migrations/006_create_email_tracking_tables.php
   ```

4. **Revert modified files:**
   - src/Services/OutlookDraftService.php (remove tracking injection)
   - src/Services/WebhookService.php (remove activation logic)
   - src/Controllers/DashboardController.php (remove tracking data)
   - index.php (remove tracking route and dependencies)

5. **Remove .env variables:**
   - APP_URL (or reset to original)
   - TRACKING_BOT_DELAY_SECONDS

## Testing

Test beacon injection:
```bash
curl -X POST http://localhost/networkemailtracking/api/draft/create \
  -H "Content-Type: application/json" \
  -d '{"user":"charlie","to":"test@example.com","subject":"Test","body":"<p>Test</p>"}'
```

Test beacon request:
```bash
curl -I http://localhost/networkemailtracking/public/img/spacer.gif?cache=abcdef0123456789abcdef0123456789
```

## Future Enhancements

1. Click tracking (track link clicks, not just opens)
2. Async event recording with message queue
3. Real-time dashboard updates via WebSocket
4. Advanced analytics (time of day, device type, location)
5. A/B testing support
6. Link shortening with tracking
7. Unsubscribe link tracking
