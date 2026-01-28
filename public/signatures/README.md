# Email Signature Images

This directory stores signature images for each user. Images are embedded as inline attachments in emails using Content-ID (CID) references.

## Structure

Each user should have their signature images stored here with consistent naming:
- `{username}-logo.png` - Main signature banner/logo (recommended max: 623x79px)
- `{username}-linkedin.png` - LinkedIn icon (40x40px)
- `{username}-instagram.png` - Instagram icon (40x40px)
- `{username}-twitter.png` - Twitter/X icon (40x40px)

## Supported Users
- charlie
- marcy
- ann
- kristen
- katie
- tameka

## Image Guidelines
- Format: PNG (supports transparency)
- Logo: Max 700x100px to keep email size reasonable
- Social icons: 40x40px standard size
- Optimize images to reduce file size (use tools like TinyPNG)
- Total signature size should stay under 100KB

## Usage
Images are automatically embedded as inline attachments when drafts are created. The HTML signature stored in the `sync_state` database table references these images using CID format: `<img src="cid:marcy-logo">`
