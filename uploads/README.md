# InterLink — Uploads Directory

This directory stores user-uploaded files.

## Structure

- `avatars/`  — User profile pictures
- `images/`   — Images shared in chat
- `files/`    — Documents and other files shared in chat

## Security Notes

- Files are renamed to random UUIDs on upload (no original names preserved in filesystem)
- MIME type validation is performed server-side before saving
- Maximum upload size: 10MB
- This directory should ideally be placed **outside the webroot** in production
- In production, serve files through a PHP proxy (not directly via Apache/Nginx)
