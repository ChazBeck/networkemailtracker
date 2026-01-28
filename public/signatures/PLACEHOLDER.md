# Example Signature Image - Placeholder

This is a placeholder file. Replace with actual signature images for each user.

## Required Images for Marcy

To use the Marcy signature that was migrated to the database, you need to add these images:

- `marcy-logo.png` - Main signature banner (623x79px)
- `marcy-linkedin.png` - LinkedIn icon (40x40px)  
- `marcy-instagram.png` - Instagram icon (40x40px)
- `marcy-twitter.png` - Twitter icon (40x40px)

## Image Requirements

- **Format**: PNG (for transparency support)
- **Logo dimensions**: Recommended 623x79px or similar aspect ratio
- **Icon dimensions**: Standard 40x40px
- **File size**: Keep under 30KB per image for fast loading

## To Add Images

1. Save your images with the exact naming format: `{username}-{type}.png`
2. Place them directly in this directory (`/public/signatures/`)
3. No subdirectories needed - all images go in the root signatures folder
4. The system will automatically find and attach them based on CID references in the signature HTML

## Testing

After adding images:
1. Go to the email drafter
2. Select the user (e.g., Marcy)
3. Compose a test email
4. Send to Outlook
5. Check the draft - images should appear inline without any download prompts

If images don't appear, check:
- File names match exactly (case-sensitive)
- File extensions are lowercase (.png not .PNG)
- Files are readable by the web server
- Check logs at `/logs/app.log` for any errors
