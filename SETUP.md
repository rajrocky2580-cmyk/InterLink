# InterLink — Setup Guide

## Quick Start (XAMPP / Laragon)

### 1. Copy Project
Place the `InterLink/` folder in your web server's root:
- **XAMPP**: `C:\xampp\htdocs\InterLink\`
- **Laragon**: `C:\laragon\www\InterLink\`

### 2. Create Database
```sql
CREATE DATABASE InterLink;
```
Then import the schema:
```
mysql -u root InterLink < sql/schema.sql
```
Or use phpMyAdmin → Import → select `sql/schema.sql`

### 3. Configure
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'InterLink');
define('DB_USER', 'root');
define('DB_PASS', '');           // your MySQL password
define('BASE_URL', 'http://localhost/InterLink');
```

### 4. Create Upload Directories
```
InterLink/uploads/avatars/
InterLink/uploads/images/
InterLink/uploads/files/
```
Or run:
```bash
mkdir -p uploads/avatars uploads/images uploads/files
```

### 5. Start Server
- Start Apache + MySQL in XAMPP Control Panel
- Visit: http://localhost/InterLink

### 6. Default Admin Account
- **Email**: admin@InterLink.local
- **Password**: `password`
> ⚠️ Change this immediately in production!

---

## Tech Stack
- PHP 8.x + PDO (MySQL)
- MySQL 8.x
- HTML5 / CSS3 / Vanilla JavaScript
- Long-polling for real-time messages (2s interval)

## Production Deployment
See `claude.md` Section 17 for full Linux VPS deployment guide.
