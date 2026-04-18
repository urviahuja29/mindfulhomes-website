# Mindful Homes Webapp

## What this is

A PHP-first marketing site and lightweight AI organizer flow designed to run on standard Hostinger hosting without Node.js.

## Configuration

Preferred:

- Set `OPENAI_API_KEY` on the server.

Fallback:

- Create `private/openai-key.php` outside the public web root and return the API key string from that file.
- Place `private/` as a sibling directory above the deployed public web root.

Example:

```php
<?php
return 'sk-...';
```

## Deployment checklist

- Upload the `mindfulhomes-webapp` contents to your Hostinger web root.
- Keep `private/openai-key.php` outside the public web root.
- Confirm PHP `curl` is enabled.
- Confirm file uploads are enabled and sized for the organizer workflow.
- Set practical PHP minimums for the current 5 x 8 MB image flow:
  `upload_max_filesize >= 8M`, `post_max_size >= 48M`, and `max_file_uploads >= 5`.
- Leave extra headroom above those minimums if the host adds multipart overhead or if larger images are expected later.
- The public organizer uses lightweight abuse controls only; if the form starts getting abused in production, add CAPTCHA and/or stronger rate limiting at the host or CDN layer.
- Run PHP lint checks before going live.
