# Chat Application — Full Project Documentation

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Tech Stack](#2-tech-stack)
3. [System Architecture](#3-system-architecture)
4. [Features List](#4-features-list)
5. [Database Design (MySQL)](#5-database-design-mysql)
6. [Folder Structure](#6-folder-structure)
7. [Module-by-Module Breakdown](#7-module-by-module-breakdown)
8. [API Endpoints](#8-api-endpoints)
9. [Real-Time Messaging (WebSocket)](#9-real-time-messaging-websocket)
10. [Frontend Pages & UI Flow](#10-frontend-pages--ui-flow)
11. [Security Considerations](#11-security-considerations)
12. [File Sharing Module](#12-file-sharing-module)
13. [Notifications Module](#13-notifications-module)
14. [Admin Panel](#14-admin-panel)
15. [Development Phases & Timeline](#15-development-phases--timeline)
16. [Testing Plan](#16-testing-plan)
17. [Deployment Guide](#17-deployment-guide)
18. [Future Enhancements](#18-future-enhancements)

---

## 1. Project Overview

**Project Name:** InterLink — Real-Time Chat Application

**Purpose:**
A full-stack web-based chat application that allows registered users to send real-time private messages and participate in group conversations. It supports text messages, file/image sharing, message read receipts, online/offline status, and an admin panel for user management.

**Target Users:**
- Teams in small companies for internal communication
- College students for project group chats
- General public as a WhatsApp-like web messenger

**Problem it Solves:**
Eliminates dependency on third-party tools like WhatsApp or Telegram for internal organizational communication. All data stays on your own server.

---

## 2. Tech Stack

| Layer | Technology | Purpose |
|---|---|---|
| Frontend | HTML5, CSS3, JavaScript (Vanilla / jQuery) | UI pages, forms, real-time DOM updates |
| Backend | PHP 8.x | Server-side logic, REST API, session handling |
| Database | MySQL 8.x | Persistent storage of users, messages, groups |
| Real-Time | JavaScript WebSocket (Ratchet PHP or native JS polling) | Live message delivery |
| Styling | CSS3 (Flexbox, Grid, custom variables) | Responsive layout |
| Server | Apache / Nginx + PHP-FPM | Hosting |
| Optional C/C++ | C/C++ compiled helper | Fast message encryption or file compression utility |

---

## 3. System Architecture

```
+------------------+        HTTP/WebSocket        +-------------------+
|                  | <---------------------------> |                   |
|  Browser Client  |                               |   PHP Backend     |
|  HTML+CSS+JS     |        REST API (JSON)        |   (Apache/Nginx)  |
|                  | <---------------------------> |                   |
+------------------+                               +--------+----------+
                                                            |
                                                            | PDO / MySQLi
                                                            |
                                                   +--------+----------+
                                                   |   MySQL Database  |
                                                   |  (messages, users,|
                                                   |   groups, files)  |
                                                   +-------------------+

WebSocket Flow (Real-Time):
Browser <---> WebSocket Server (PHP Ratchet on port 8080) <---> MySQL
```

**Request Flow for Sending a Message:**
1. User types message → JavaScript captures `keypress Enter`
2. JS sends POST request to `api/messages/send.php`
3. PHP validates session, sanitizes input, inserts row into `messages` table
4. PHP pushes event to WebSocket server
5. WebSocket server broadcasts to recipient's connected socket
6. Recipient's browser JS receives event → appends message to DOM

---

## 4. Features List

### Core Features (Must Have)
- User Registration & Login (with hashed passwords)
- Private 1-on-1 Chat
- Group Chat (create group, add/remove members)
- Real-time message delivery
- Message read receipts (Sent ✓, Delivered ✓✓, Read ✓✓ blue)
- Online / Offline / Last Seen status
- Message timestamps
- Conversation list with last message preview
- User search by name or username
- Emoji support

### Extended Features (Should Have)
- File and image sharing (up to 10MB)
- Message delete (delete for me / delete for everyone)
- Edit sent messages (within 5 minutes)
- Reply to a specific message (quoted reply)
- User profile page (avatar, bio, phone)
- Push / browser notifications for new messages
- Unread message count badge

### Admin Features
- View all registered users
- Deactivate / ban a user
- View reported messages
- Message logs (with filter by date/user)
- System stats dashboard (total users, messages today, active groups)

### Optional / Future Features
- End-to-end encryption (using C/C++ OpenSSL helper)
- Voice note recording (Web Audio API)
- Message reactions (like, love, haha)
- Dark / Light theme toggle
- Mobile PWA support

---

## 5. Database Design (MySQL)

### 5.1 users

```sql
CREATE TABLE users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) UNIQUE NOT NULL,
    email         VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,         -- bcrypt hash
    full_name     VARCHAR(100),
    avatar_url    VARCHAR(255) DEFAULT 'default.png',
    bio           TEXT,
    phone         VARCHAR(20),
    status        ENUM('active','banned','deactivated') DEFAULT 'active',
    is_online     TINYINT(1) DEFAULT 0,
    last_seen     DATETIME,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME ON UPDATE CURRENT_TIMESTAMP
);
```

### 5.2 conversations

```sql
CREATE TABLE conversations (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    type            ENUM('private','group') NOT NULL,
    created_by      INT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);
```

### 5.3 conversation_members

```sql
CREATE TABLE conversation_members (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id         INT NOT NULL,
    role            ENUM('member','admin') DEFAULT 'member',
    joined_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    left_at         DATETIME DEFAULT NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    UNIQUE KEY unique_member (conversation_id, user_id)
);
```

### 5.4 messages

```sql
CREATE TABLE messages (
    message_id      INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id       INT NOT NULL,
    message_type    ENUM('text','image','file','audio','system') DEFAULT 'text',
    content         TEXT,                        -- message text or file URL
    reply_to        INT DEFAULT NULL,            -- FK to message_id for quoted replies
    is_edited       TINYINT(1) DEFAULT 0,
    is_deleted      TINYINT(1) DEFAULT 0,        -- soft delete
    sent_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id),
    FOREIGN KEY (sender_id) REFERENCES users(user_id),
    FOREIGN KEY (reply_to) REFERENCES messages(message_id)
);
```

### 5.5 message_status

```sql
CREATE TABLE message_status (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    message_id  INT NOT NULL,
    user_id     INT NOT NULL,                    -- recipient
    delivered   TINYINT(1) DEFAULT 0,
    read_at     DATETIME DEFAULT NULL,
    FOREIGN KEY (message_id) REFERENCES messages(message_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    UNIQUE KEY unique_status (message_id, user_id)
);
```

### 5.6 groups (metadata for group conversations)

```sql
CREATE TABLE groups (
    group_id        INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    description     TEXT,
    avatar_url      VARCHAR(255) DEFAULT 'group_default.png',
    created_by      INT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);
```

### 5.7 files

```sql
CREATE TABLE files (
    file_id         INT AUTO_INCREMENT PRIMARY KEY,
    message_id      INT NOT NULL,
    uploaded_by     INT NOT NULL,
    original_name   VARCHAR(255),
    stored_name     VARCHAR(255),                -- UUID-based stored filename
    file_type       VARCHAR(50),                 -- MIME type
    file_size       INT,                         -- bytes
    uploaded_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(message_id),
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
);
```

### 5.8 notifications

```sql
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,                -- recipient
    type            VARCHAR(50),                 -- 'new_message', 'group_invite', etc.
    reference_id    INT,                         -- message_id or group_id
    message         VARCHAR(255),
    is_read         TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);
```

### 5.9 reports

```sql
CREATE TABLE reports (
    report_id       INT AUTO_INCREMENT PRIMARY KEY,
    reported_by     INT NOT NULL,
    reported_user   INT,
    message_id      INT,
    reason          TEXT,
    status          ENUM('pending','reviewed','dismissed') DEFAULT 'pending',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reported_by) REFERENCES users(user_id)
);
```

### Entity Relationship Summary

```
users ──< conversation_members >── conversations
                                         |
                                      messages
                                         |
                                   message_status
                                         |
                                        files

conversations ──< groups (1:1 for group type)
users ──< notifications
users ──< reports
```

---

## 6. Folder Structure

```
chattterbox/
│
├── index.php                  # Landing / Login page
├── register.php               # Registration page
├── chat.php                   # Main chat interface (protected)
├── profile.php                # User profile page
├── logout.php                 # Session destroy + redirect
│
├── admin/
│   ├── index.php              # Admin dashboard
│   ├── users.php              # Manage users
│   ├── messages.php           # Message logs
│   ├── reports.php            # Reported content
│   └── stats.php              # System statistics
│
├── api/
│   ├── auth/
│   │   ├── login.php          # POST: login, return session
│   │   ├── register.php       # POST: create new user
│   │   └── logout.php         # POST: destroy session
│   ├── users/
│   │   ├── search.php         # GET: search users by username
│   │   ├── profile.php        # GET/PUT: view or update profile
│   │   └── status.php         # POST: update online status
│   ├── conversations/
│   │   ├── list.php           # GET: all conversations for logged-in user
│   │   ├── create.php         # POST: create private or group conversation
│   │   └── members.php        # GET/POST/DELETE: manage group members
│   ├── messages/
│   │   ├── send.php           # POST: send a message
│   │   ├── fetch.php          # GET: fetch messages for a conversation
│   │   ├── delete.php         # DELETE: soft delete a message
│   │   ├── edit.php           # PUT: edit a message
│   │   └── read.php           # POST: mark messages as read
│   ├── files/
│   │   └── upload.php         # POST: upload file, return URL
│   └── notifications/
│       ├── fetch.php          # GET: get unread notifications
│       └── mark_read.php      # POST: mark notifications read
│
├── includes/
│   ├── db.php                 # PDO database connection
│   ├── auth.php               # Session check helper
│   ├── helpers.php            # Sanitize, format date, etc.
│   └── config.php             # DB credentials, constants
│
├── assets/
│   ├── css/
│   │   ├── main.css           # Global styles
│   │   ├── chat.css           # Chat interface styles
│   │   └── admin.css          # Admin panel styles
│   ├── js/
│   │   ├── app.js             # Main app logic
│   │   ├── chat.js            # Message send/receive, WebSocket
│   │   ├── notifications.js   # Notification handling
│   │   └── upload.js          # File upload with preview
│   └── images/
│       ├── default.png        # Default user avatar
│       └── group_default.png  # Default group avatar
│
├── uploads/                   # Uploaded files (outside webroot ideally)
│   ├── avatars/
│   └── files/
│
├── websocket/
│   └── server.php             # Ratchet WebSocket server (run via CLI)
│
└── sql/
    └── schema.sql             # Full DB schema to import
```

---

## 7. Module-by-Module Breakdown

### Module 1: Authentication

**Files:** `index.php`, `register.php`, `api/auth/login.php`, `api/auth/register.php`

**Registration Flow:**
1. User fills form: username, email, password, full name
2. Client-side JS validates (non-empty, valid email, password ≥ 8 chars)
3. POST to `api/auth/register.php`
4. PHP checks: does username/email already exist?
5. Hash password with `password_hash($pass, PASSWORD_BCRYPT)`
6. Insert into `users` table
7. Return JSON `{success: true, user_id: X}`
8. Redirect to `chat.php`

**Login Flow:**
1. User enters email + password
2. POST to `api/auth/login.php`
3. PHP fetches user by email, runs `password_verify()`
4. If match: create session `$_SESSION['user_id']`, set `is_online=1`, `last_seen=NOW()`
5. Return JSON `{success: true}`, JS redirects to `chat.php`
6. If fail: return `{success: false, error: "Invalid credentials"}`

**Session Guard (includes/auth.php):**
```php
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /index.php');
        exit;
    }
}
```
Call `requireLogin()` at top of every protected page and API endpoint.

---

### Module 2: Conversation List

**Files:** `api/conversations/list.php`, `assets/js/app.js`

**Backend Query:**
```sql
SELECT 
    c.conversation_id,
    c.type,
    CASE 
        WHEN c.type = 'private' THEN u.full_name
        ELSE g.name
    END AS display_name,
    CASE 
        WHEN c.type = 'private' THEN u.avatar_url
        ELSE g.avatar_url
    END AS avatar,
    m.content AS last_message,
    m.sent_at,
    (SELECT COUNT(*) FROM message_status ms 
     WHERE ms.user_id = ? AND ms.read_at IS NULL
     AND ms.message_id IN (SELECT message_id FROM messages WHERE conversation_id = c.conversation_id)
    ) AS unread_count
FROM conversations c
JOIN conversation_members cm ON c.conversation_id = cm.conversation_id
LEFT JOIN conversation_members cm2 ON c.conversation_id = cm2.conversation_id 
    AND cm2.user_id != ? AND c.type = 'private'
LEFT JOIN users u ON cm2.user_id = u.user_id
LEFT JOIN groups g ON c.conversation_id = g.conversation_id
LEFT JOIN messages m ON m.message_id = (
    SELECT message_id FROM messages 
    WHERE conversation_id = c.conversation_id 
    ORDER BY sent_at DESC LIMIT 1
)
WHERE cm.user_id = ? AND cm.left_at IS NULL
ORDER BY m.sent_at DESC;
```

**Frontend rendering:** JS loops over JSON response, builds sidebar HTML cards showing avatar, name, last message preview, time, and unread badge.

---

### Module 3: Real-Time Messaging

**Files:** `api/messages/send.php`, `api/messages/fetch.php`, `assets/js/chat.js`, `websocket/server.php`

**Sending a Message:**
```javascript
// chat.js
function sendMessage(conversationId, content) {
    fetch('/api/messages/send.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: conversationId, content: content })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            appendMessageToUI(data.message);  // Show immediately for sender
        }
    });
}
```

**PHP send.php logic:**
```php
// Sanitize input
$content = htmlspecialchars(trim($_POST['content']));
$conv_id = (int)$_POST['conversation_id'];
$sender  = $_SESSION['user_id'];

// Verify sender is a member of this conversation
$stmt = $pdo->prepare("SELECT 1 FROM conversation_members WHERE conversation_id=? AND user_id=?");
$stmt->execute([$conv_id, $sender]);
if (!$stmt->fetch()) { http_response_code(403); exit; }

// Insert message
$stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, content) VALUES (?,?,?)");
$stmt->execute([$conv_id, $sender, $content]);
$msg_id = $pdo->lastInsertId();

// Insert message_status rows for all other members
$members = getConversationMembers($conv_id, $pdo);
foreach ($members as $member_id) {
    if ($member_id != $sender) {
        $pdo->prepare("INSERT INTO message_status (message_id, user_id) VALUES (?,?)")
            ->execute([$msg_id, $member_id]);
    }
}

// Notify WebSocket server
notifyWebSocket(['type'=>'new_message', 'conversation_id'=>$conv_id, 'message_id'=>$msg_id]);

echo json_encode(['success'=>true, 'message_id'=>$msg_id]);
```

**Polling Alternative (if WebSocket not set up):**
Use `setInterval` to call `api/messages/fetch.php?after=<last_message_id>` every 2 seconds. Less efficient but simpler to deploy.

---

### Module 4: Group Chat

**Files:** `api/conversations/create.php`, `api/conversations/members.php`

**Create Group:**
1. User selects multiple contacts, gives group a name
2. POST `{type: 'group', name: 'Project Team', members: [2,5,8]}`
3. PHP inserts into `conversations` (type=group)
4. Inserts into `groups` (name, created_by)
5. Inserts creator as admin, others as members in `conversation_members`
6. Returns `{conversation_id: X}`

**Admin Actions on Group:**
- Add member: INSERT into `conversation_members`
- Remove member: UPDATE `left_at = NOW()` (soft remove)
- Promote to admin: UPDATE `role = 'admin'`
- Leave group: same as remove but for self

---

### Module 5: File Sharing

See [Section 12](#12-file-sharing-module) for full details.

---

### Module 6: User Profile

**Files:** `profile.php`, `api/users/profile.php`

**Editable fields:** full_name, bio, avatar (image upload), phone

**Avatar upload:** File uploaded to `uploads/avatars/`, stored as `{user_id}_{timestamp}.jpg`. Old avatar deleted. URL saved in DB.

**Viewing other profiles:** GET `api/users/profile.php?user_id=X` returns public info (name, avatar, last_seen, bio). Used when clicking a user's name in chat.

---

## 8. API Endpoints

All endpoints return JSON. All except `/api/auth/*` require a valid session.

| Method | Endpoint | Description | Body / Params |
|---|---|---|---|
| POST | /api/auth/register.php | Register new user | username, email, password, full_name |
| POST | /api/auth/login.php | Login | email, password |
| POST | /api/auth/logout.php | Logout | — |
| GET | /api/users/search.php | Search users | ?q=searchterm |
| GET | /api/users/profile.php | Get user profile | ?user_id=X |
| PUT | /api/users/profile.php | Update own profile | full_name, bio, phone |
| POST | /api/users/status.php | Update online status | status: online/offline |
| GET | /api/conversations/list.php | Get all conversations | — |
| POST | /api/conversations/create.php | Create conversation | type, members[], name (group) |
| GET | /api/conversations/members.php | List group members | ?conversation_id=X |
| POST | /api/conversations/members.php | Add member to group | conversation_id, user_id |
| DELETE | /api/conversations/members.php | Remove member | conversation_id, user_id |
| POST | /api/messages/send.php | Send message | conversation_id, content, type |
| GET | /api/messages/fetch.php | Fetch messages | ?conversation_id=X&after=msg_id |
| DELETE | /api/messages/delete.php | Delete message | message_id, scope (me/all) |
| PUT | /api/messages/edit.php | Edit message | message_id, content |
| POST | /api/messages/read.php | Mark messages read | conversation_id |
| POST | /api/files/upload.php | Upload file | multipart file |
| GET | /api/notifications/fetch.php | Get notifications | — |
| POST | /api/notifications/mark_read.php | Mark notifications read | notification_ids[] |

---

## 9. Real-Time Messaging (WebSocket)

### Option A: PHP Ratchet (Recommended)

Install via Composer:
```bash
composer require cboden/ratchet
```

**websocket/server.php:**
```php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $userConnections = []; // user_id => Connection

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        // Register user session
        if ($data['type'] === 'auth') {
            $this->userConnections[$data['user_id']] = $from;
        }

        // Broadcast to conversation members
        if ($data['type'] === 'message') {
            foreach ($data['recipients'] as $uid) {
                if (isset($this->userConnections[$uid])) {
                    $this->userConnections[$uid]->send(json_encode($data));
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        // Remove from userConnections
        foreach ($this->userConnections as $uid => $c) {
            if ($c === $conn) unset($this->userConnections[$uid]);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }
}

$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(new Chat())
    ), 8080
);
$server->run();
```

Run server: `php websocket/server.php`

### Option B: Long Polling (Simpler, No Server Dependency)

```javascript
// chat.js - poll every 2 seconds
let lastMessageId = 0;

setInterval(() => {
    fetch(`/api/messages/fetch.php?conversation_id=${currentConvId}&after=${lastMessageId}`)
    .then(r => r.json())
    .then(data => {
        data.messages.forEach(msg => {
            appendMessage(msg);
            lastMessageId = Math.max(lastMessageId, msg.message_id);
        });
    });
}, 2000);
```

---

## 10. Frontend Pages & UI Flow

### Page: index.php (Login)

- Clean centered card with logo
- Email + password fields
- "Login" button → AJAX POST → redirect to chat.php
- "Register" link → register.php
- Error message displayed inline (wrong credentials)

### Page: register.php

- Fields: Full Name, Username, Email, Password, Confirm Password
- Real-time validation feedback (username available check via AJAX)
- Submit → AJAX → auto-login → redirect to chat.php

### Page: chat.php (Main UI Layout)

```
+-----------------------------+------------------------------------------+
|  Logo + Search bar          |  Conversation header (name, avatar, status)|
|-----------------------------|------------------------------------------|
|  [Conversation List]        |  [Message Thread Area]                   |
|  - Avatar, Name             |  Each message bubble:                    |
|  - Last message preview     |  - Left: received (gray bubble)          |
|  - Time, Unread badge       |  - Right: sent (colored bubble)          |
|  [+ New Chat button]        |  - Timestamp, read receipt icons         |
|                             |------------------------------------------|
|                             |  [Emoji] [Attach] [Input Box] [Send Btn] |
+-----------------------------+------------------------------------------+
```

**CSS Layout:** CSS Grid for two-column split. Flexbox for message bubbles. `overflow-y: scroll` on message area with `scroll-behavior: smooth`.

**Scroll to Bottom:** Auto-scroll when new message arrives if user is already near bottom.

### Message Bubble HTML:
```html
<!-- Received message -->
<div class="message received">
    <img src="avatar.png" class="msg-avatar">
    <div class="msg-body">
        <p class="msg-text">Hello! How are you?</p>
        <span class="msg-time">10:32 AM</span>
    </div>
</div>

<!-- Sent message -->
<div class="message sent">
    <div class="msg-body">
        <p class="msg-text">I'm good, thanks!</p>
        <span class="msg-time">10:33 AM <i class="read-tick">✓✓</i></span>
    </div>
</div>
```

---

## 11. Security Considerations

| Threat | Mitigation |
|---|---|
| SQL Injection | Use PDO Prepared Statements everywhere. Never concatenate user input in SQL. |
| XSS (Cross-Site Scripting) | `htmlspecialchars()` all output. Content Security Policy headers. |
| CSRF (Cross-Site Request Forgery) | Generate and validate CSRF token in all POST forms and AJAX calls. |
| Brute Force Login | Rate limit login attempts: max 5 per minute per IP. Use session or DB counter. |
| Unauthorized API Access | Every API endpoint checks `$_SESSION['user_id']`. Verify conversation membership before message actions. |
| Insecure File Uploads | Validate MIME type (not just extension). Rename files to UUID. Store outside webroot. Serve via secure PHP proxy. |
| Password Storage | `password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])` — never MD5/SHA1. |
| Session Hijacking | `session_regenerate_id(true)` after login. Set `HttpOnly` and `Secure` cookie flags. |
| Mass Assignment | Never use `$_POST` directly in DB queries. Whitelist allowed fields explicitly. |

**CSRF Token Pattern:**
```php
// In form/page generation:
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
echo '<input type="hidden" name="csrf" value="' . $_SESSION['csrf_token'] . '">';

// In API handler:
if ($_POST['csrf'] !== $_SESSION['csrf_token']) {
    http_response_code(403); exit;
}
```

---

## 12. File Sharing Module

**Allowed Types:** jpg, jpeg, png, gif, pdf, doc, docx, mp3, mp4 (max 10MB)

**Upload Flow:**
1. User clicks attach icon → file picker opens
2. JS reads file, shows preview (image thumbnail or file icon)
3. On send: upload file to `api/files/upload.php` (multipart form data)
4. PHP validates type, size → renames to `uploads/files/{UUID}.ext`
5. Inserts into `files` table, inserts message with type=image/file and content=file URL
6. Returns message JSON to display in chat

**PHP Upload Handler:**
```php
$allowed_types = ['image/jpeg','image/png','image/gif','application/pdf','audio/mpeg'];
$max_size = 10 * 1024 * 1024; // 10MB

if (!in_array($_FILES['file']['type'], $allowed_types)) {
    echo json_encode(['error' => 'File type not allowed']); exit;
}
if ($_FILES['file']['size'] > $max_size) {
    echo json_encode(['error' => 'File too large']); exit;
}

$ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
$stored_name = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
$dest = __DIR__ . '/../../uploads/files/' . $stored_name;

move_uploaded_file($_FILES['file']['tmp_name'], $dest);
// Insert into files and messages tables...
```

---

## 13. Notifications Module

**Browser Notifications (Web Push):**
1. On page load, request permission: `Notification.requestPermission()`
2. When new message arrives via WebSocket (and window not focused):
```javascript
if (document.hidden && Notification.permission === 'granted') {
    new Notification(senderName, {
        body: messagePreview,
        icon: senderAvatar
    });
}
```

**In-app Notification Bell:**
- Poll `api/notifications/fetch.php` every 30 seconds
- Show count badge on bell icon
- Dropdown list of unread notifications
- Click notification → open that conversation

---

## 14. Admin Panel

**Files:** `admin/*.php` — protected by separate admin session check (role column or separate admin table)

**Dashboard (`admin/index.php`):**
- Total registered users
- Messages sent today
- Active conversations
- Files uploaded today
- Recent registrations table

**User Management (`admin/users.php`):**
- Table of all users with columns: ID, Name, Email, Status, Registered, Last Seen
- Actions: View Profile, Deactivate, Ban, Delete
- Search/filter bar

**Message Logs (`admin/messages.php`):**
- Filter by: date range, user, conversation
- View message content, sender, timestamp
- Mark as reviewed if reported

**Reports (`admin/reports.php`):**
- List of reported messages/users
- Status: Pending / Reviewed / Dismissed
- Admin can take action (warn user, delete message, ban user)

---

## 15. Development Phases & Timeline

### Phase 1 — Foundation (Week 1-2)
- Set up project folder, Apache/MySQL environment
- Create database schema (`sql/schema.sql`)
- Build `includes/db.php` connection and helper functions
- Registration, Login, Logout (auth module)
- Basic `chat.php` layout (HTML/CSS skeleton)

### Phase 2 — Core Chat (Week 3-4)
- Conversation list API + frontend rendering
- Private conversation creation
- Message send API + frontend send/receive
- Message fetch with polling
- Basic message bubble UI

### Phase 3 — Real-Time & Groups (Week 5-6)
- WebSocket server setup (Ratchet)
- Replace polling with WebSocket in JS
- Group chat creation and management
- Read receipts and online status

### Phase 4 — Extended Features (Week 7-8)
- File/image upload and display
- Message delete and edit
- Reply to message (quoted reply)
- User profile page and avatar upload
- Emoji picker integration

### Phase 5 — Notifications & Polish (Week 9)
- Browser push notifications
- In-app notification bell
- Unread count badges
- Responsive CSS improvements

### Phase 6 — Admin Panel (Week 10)
- Admin dashboard, user management
- Message logs and reports
- Final security audit (CSRF, XSS, file upload validation)

### Phase 7 — Testing & Deployment (Week 11-12)
- Cross-browser testing
- Load test with multiple simultaneous users
- Deploy to live server
- Write user documentation

---

## 16. Testing Plan

### Unit Tests
- Auth: register with existing email → should return error
- Auth: login with wrong password → should return 401
- Message: send to conversation user is not a member of → should return 403
- File: upload executable file → should be rejected

### Functional Tests
| Test Case | Expected Result |
|---|---|
| Register new user | User created, redirected to chat |
| Login with valid credentials | Session created, chat page loads |
| Send message | Message appears in both sender and recipient windows |
| Create group, add members | Group appears in all members' conversation lists |
| Upload image | Thumbnail displayed in chat |
| Delete message for everyone | Message shows "This message was deleted" for all |
| Ban user (admin) | Banned user cannot login |

### Performance Tests
- Simulate 50 concurrent WebSocket connections
- Send 1000 messages and measure DB query time
- Check conversation list load time with 200+ conversations

---

## 17. Deployment Guide

### Local Development
```bash
# Requirements: XAMPP or Laragon
# 1. Copy project to htdocs/InterLink
# 2. Import database
mysql -u root -p InterLink < sql/schema.sql
# 3. Edit includes/config.php with your DB credentials
# 4. Start Apache + MySQL
# 5. Visit http://localhost/InterLink
```

### Production Server (Linux VPS)
```bash
# Install dependencies
sudo apt update
sudo apt install apache2 mysql-server php8.2 php8.2-mysql composer -y

# Clone project
git clone https://github.com/yourname/InterLink /var/www/html/InterLink

# Import DB
mysql -u root -p < sql/schema.sql

# Set permissions
sudo chown -R www-data:www-data /var/www/html/InterLink/uploads
sudo chmod -R 755 /var/www/html/InterLink/uploads

# Start WebSocket server as background process
php /var/www/html/InterLink/websocket/server.php &

# Or use supervisor to keep WebSocket running
# /etc/supervisor/conf.d/InterLink_ws.conf
```

**includes/config.php:**
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'InterLink');
define('DB_USER', 'InterLink_user');
define('DB_PASS', 'strongpassword123');
define('UPLOAD_PATH', '/var/www/html/InterLink/uploads/');
define('WS_PORT', 8080);
define('BASE_URL', 'https://yourdomain.com');
```

---

## 18. Future Enhancements

| Feature | Tech Required |
|---|---|
| End-to-End Encryption | C/C++ OpenSSL library compiled as PHP extension |
| Voice & Video Calls | WebRTC (JavaScript), TURN/STUN server |
| Message Reactions | Add reactions table in MySQL, update UI |
| Message Search | MySQL FULLTEXT index on messages.content |
| Mobile App | React Native or Flutter consuming the same PHP API |
| Multi-language support | i18n JSON files, language switcher |
| Email OTP verification | PHPMailer integration on register |
| OAuth Login | Google/GitHub login via OAuth2 PHP library |
| Chatbot Integration | Claude API integration for AI assistant in chat |
| Message Scheduling | Cron job + scheduled_messages table |

---

*Document Version: 1.0 | Project: InterLink Chat Application | Stack: PHP, MySQL, JavaScript, HTML, CSS*
