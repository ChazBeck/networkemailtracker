# Email Signature Implementation Guide

## Overview

Email signatures with inline images are now automatically appended to all drafts created through the email drafter. Signatures are stored in the database and images are embedded as inline attachments using Microsoft Graph API, ensuring recipients see images without needing to download external content.

## Architecture

### Flow
1. User composes email in drafter → `email-drafter.php`
2. Submit creates draft → `DraftController::create()`
3. Service retrieves signature → `OutlookDraftService::getUserSignature()`
4. Signature HTML appended → After sanitization, before link tracking
5. Draft created via Graph API → Returns message ID
6. Images attached inline → `OutlookDraftService::addInlineAttachment()` for each CID reference
7. Final draft ready in Outlook with embedded images

### Key Components

**OutlookDraftService** (`src/Services/OutlookDraftService.php`)
- `getUserSignature()` - Fetches signature HTML from `sync_state` table and parses CID references
- `addInlineAttachment()` - Uploads images as inline attachments to existing draft
- `createDraft()` - Modified for two-step process: create draft, then attach images

**Database Storage** (`sync_state` table)
- Key: `signature_{username}` (e.g., `signature_marcy`)
- Value: HTML with CID image references (e.g., `<img src="cid:marcy-logo">`)

**Image Storage** (`public/signatures/`)
- Images named by CID: `{username}-logo.png`, `{username}-linkedin.png`, etc.
- Automatically detected with extensions: .png, .jpg, .jpeg, .gif

## Setup for New Users

### 1. Create Signature Images

Place images in `/public/signatures/` with naming convention:
```
{username}-logo.png       # Main signature banner (623x79px recommended)
{username}-linkedin.png   # LinkedIn icon (40x40px)
{username}-instagram.png  # Instagram icon (40x40px)  
{username}-twitter.png    # Twitter/X icon (40x40px)
```

Example for user "charlie":
- `charlie-logo.png`
- `charlie-linkedin.png`
- `charlie-instagram.png`
- `charlie-twitter.png`

### 2. Insert Signature HTML

Add signature to database using CID references:

```sql
INSERT INTO sync_state (name, value, updated_at) VALUES (
    'signature_charlie',
    '<table style="box-sizing: border-box; border-collapse: collapse; border-spacing: 0px;">
    <tbody>
    <tr>
    <td colspan="3" style="padding: 0in 5.4pt; vertical-align: top;">
    <img src="cid:charlie-logo" width="623" height="79">
    </td>
    </tr>
    <tr>
    <td style="padding: 0in 5.4pt; vertical-align: top; width: 2.5in;">
    <span style="font-family: Arial Narrow, sans-serif; font-size: 10pt;">
    <i>Mobile</i>: 555-123-4567<br>
    <i>Email</i>: <a href="mailto:charlie@veerless.com">charlie@veerless.com</a><br>
    <i>Website</i>: <a href="http://www.veerless.com/">www.veerless.com</a>
    </span>
    </td>
    <td style="padding: 0in 5.4pt; vertical-align: top;">
    <a href="http://www.linkedin.com/in/charlieveerless">
    <img src="cid:charlie-linkedin" width="40" height="40">
    </a>
    <a href="http://www.instagram.com/meetveerless">
    <img src="cid:charlie-instagram" width="40" height="40">
    </a>
    <a href="http://www.twitter.com/charlieveerless">
    <img src="cid:charlie-twitter" width="40" height="40">
    </a>
    </td>
    </tr>
    </tbody>
    </table>',
    NOW()
) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW();
```

**Important**: Use `cid:{imagename}` format in `src` attributes. The image filename must match the CID (minus extension).

### 3. Test the Signature

1. Go to email drafter: `http://localhost/networkemailtracking/email-drafter.php`
2. Select the user (e.g., Charlie)
3. Compose a test email
4. Click "Send to Outlook"
5. Check Outlook drafts - signature should appear with all images embedded

## Current Signatures

Default signatures have been created for all 6 users:

- **Marcy** - Full signature with logo and social icons (based on production email)
- **Charlie** - Template with placeholders (customize phone number and LinkedIn URL)
- **Ann** - Template with placeholders
- **Kristen** - Simple text-only signature (no images)
- **Katie** - Simple text-only signature (no images)
- **Tameka** - Simple text-only signature (no images)

## Customizing Signatures

### Option A: Direct Database Update

```sql
UPDATE sync_state 
SET value = '<your HTML here>', updated_at = NOW() 
WHERE name = 'signature_marcy';
```

### Option B: Re-run Migration

Edit `migrations/009_insert_email_signatures.sql` and re-run:
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root email_tracking_local < migrations/009_insert_email_signatures.sql
```

### Option C: Create Signature Manager UI (Future Enhancement)

Build an admin page at `signature-manager.php` with:
- List all users
- Rich text editor (Quill) for each signature
- Image upload interface
- Preview functionality

## Technical Details

### CID (Content-ID) Format

Images use Content-ID references following RFC 2392:
- HTML: `<img src="cid:marcy-logo">`
- Attachment: `contentId: "marcy-logo"`, `isInline: true`

### Image Processing

When a draft is created:
1. `getUserSignature()` parses signature HTML for all `cid:*` references
2. Matches each CID to file in `/public/signatures/` (tries .png, .jpg, .jpeg, .gif)
3. Returns array: `['html' => '...', 'attachments' => [['cid' => '...', 'path' => '...']]]`
4. Each attachment is uploaded via Graph API `/messages/{id}/attachments` endpoint

### Graph API Attachment Format

```json
{
  "@odata.type": "#microsoft.graph.fileAttachment",
  "name": "marcy-logo.png",
  "contentType": "image/png",
  "contentBytes": "iVBORw0KG... (base64)",
  "contentId": "marcy-logo",
  "isInline": true
}
```

### HTML Sanitization

The following tags are allowed for signatures:
- Structure: `<p>`, `<br>`, `<span>`, `<div>`, `<table>`, `<tbody>`, `<tr>`, `<td>`
- Formatting: `<strong>`, `<em>`, `<u>`, `<s>`, `<i>`
- Links: `<a>` (href validated, no javascript:)
- Images: `<img>` (src validated, no javascript:)
- Lists: `<ul>`, `<ol>`, `<li>`
- Headings: `<h1>` through `<h6>`

Style attributes are preserved for layout (padding, font-family, etc.).

## Best Practices

### Image Guidelines
- **Format**: PNG (supports transparency for logos)
- **Logo**: Max 700x100px, keep under 30KB
- **Icons**: 40x40px standard, under 5KB each
- **Total**: Keep total signature under 100KB for fast loading
- **Optimization**: Use tools like TinyPNG or ImageOptim

### HTML Guidelines
- Use tables for layout (best email client compatibility)
- Inline styles only (no `<style>` tags or external CSS)
- Test in multiple clients (Outlook, Gmail, Apple Mail)
- Keep width under 700px for mobile compatibility
- Use web-safe fonts or provide fallbacks

### Content Guidelines
- Include: Name, title, phone, email, website
- Optional: Social media icons, company logo, headshot
- Avoid: Large images, animated GIFs, videos, excessive links
- Accessibility: Always include `alt` text for images

## Troubleshooting

### Signature Not Appearing
1. Check database: `SELECT * FROM sync_state WHERE name LIKE 'signature_%';`
2. Verify image files exist in `/public/signatures/`
3. Check logs: `tail -f logs/app.log`
4. Ensure CID names match filename (without extension)

### Images Not Loading
1. Verify file extensions are correct (.png, .jpg, etc.)
2. Check file permissions (must be readable by web server)
3. Review attachment errors in logs
4. Test with smaller images (large files may fail silently)

### HTML Breaking
1. Validate HTML (check for unclosed tags)
2. Escape special characters in SQL (`'` becomes `''`)
3. Test signature HTML in isolation first
4. Use online HTML validators

### Graph API Errors
1. Check token expiration: `ls -la logs/graph_token.json`
2. Verify MS Graph permissions include `Mail.ReadWrite`
3. Review API response in logs
4. Test with Graph Explorer: https://developer.microsoft.com/en-us/graph/graph-explorer

## Future Enhancements

### Phase 1: Signature Manager UI
- Create `signature-manager.php` page
- Add Quill editor for WYSIWYG editing
- Upload interface for images
- Preview before saving

### Phase 2: Advanced Features
- Multiple signature templates per user
- Variable substitution: `{{name}}`, `{{phone}}`, `{{title}}`
- A/B testing different signatures
- Signature scheduling (different times/campaigns)
- Analytics (track signature link clicks separately)

### Phase 3: Personalization
- Dynamic signatures based on recipient
- Campaign-specific signatures
- Integration with Monday.com for contact data
- Conditional content (show/hide based on context)

## Migration Reference

**File**: `migrations/009_insert_email_signatures.sql`

This migration:
- Inserts default signatures for all 6 users
- Uses `ON DUPLICATE KEY UPDATE` for safe re-runs
- Includes Marcy's production signature
- Provides templates for other users

Run again anytime to reset signatures:
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root email_tracking_local < migrations/009_insert_email_signatures.sql
```

## Additional Resources

- [Microsoft Graph Attachments](https://learn.microsoft.com/en-us/graph/api/message-post-attachments)
- [Email HTML Best Practices](https://www.campaignmonitor.com/dev-resources/guides/coding-html-emails/)
- [CID URL Scheme RFC 2392](https://www.rfc-editor.org/rfc/rfc2392)
- [Outlook HTML Support](https://learn.microsoft.com/en-us/previous-versions/office/developer/office-2007/aa338201(v=office.12))
