<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';
requireLogin();

$userName   = $_SESSION['full_name'] ?? 'User';
$userAvatar = $_SESSION['avatar'] ?? 'default.png';
$userId     = currentUserId();
$userRole   = currentUserRole();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title>InterLink — Messenger</title>
  <meta name="description" content="InterLink real-time messenger — chat privately and in groups.">
  <link rel="icon" href="assets/images/favicon.png" type="image/png">
  <link rel="stylesheet" href="assets/css/main.css">
  <link rel="stylesheet" href="assets/css/chat.css">
</head>
<body>

<div class="chat-layout">

  <!-- ==================== SIDEBAR ==================== -->
  <aside class="sidebar" id="sidebar">

    <!-- Sidebar Header -->
    <div class="sidebar-header">
      <div class="sidebar-logo">
        <div class="logo-icon">💬</div>
        Inter<span>Link</span>
      </div>
      <div class="sidebar-actions">
        <!-- Notifications Bell -->
        <div class="relative">
          <button class="btn-icon" title="Notifications" onclick="App.toggleNotifications()" id="notif-btn">🔔
            <span class="badge hidden" id="notif-badge" style="position:absolute;top:-4px;right:-4px;min-width:16px;height:16px;font-size:.6rem;padding:0 4px"></span>
          </button>
        </div>
        <button class="btn-icon" title="New Group" onclick="App.showNewGroupModal()">👥</button>
        <?php if ($userRole === 'admin'): ?>
        <a href="admin/" class="btn-icon" title="Admin Panel">⚙️</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Sidebar Tabs -->
    <div class="sidebar-tabs">
      <button class="sidebar-tab active" id="tab-chats" onclick="App.switchTab('chats')">
        💬 Chats
        <span class="tab-badge hidden" id="chats-unread-badge"></span>
      </button>
      <button class="sidebar-tab" id="tab-friends" onclick="App.switchTab('friends')">
        🤝 Friends
        <span class="tab-badge hidden" id="friends-req-badge"></span>
      </button>
    </div>

    <!-- Chat Tab Panel -->
    <div id="panel-chats" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
      <!-- Search Bar -->
      <div class="search-bar relative">
        <span class="search-icon">🔍</span>
        <input type="text" id="user-search" placeholder="Search users or conversations…" autocomplete="off">
        <div id="search-results" class="search-results hidden"></div>
      </div>
      <!-- Conversation List -->
      <div class="conv-list" id="conv-list">
        <div style="display:flex;justify-content:center;padding:40px"><div class="spinner"></div></div>
      </div>
    </div>

    <!-- Friends Tab Panel -->
    <div id="panel-friends" style="display:none;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
      <div class="search-bar relative">
        <span class="search-icon">🔍</span>
        <input type="text" id="people-search" placeholder="Find people by name or @username…" autocomplete="off">
        <div id="people-results" class="search-results hidden"></div>
      </div>
      <div id="friends-panel" class="conv-list">
        <div style="text-align:center;padding:40px;color:var(--text-muted)">
          <div style="font-size:2.5rem;margin-bottom:8px">🤝</div>
          <p>Your friends will appear here</p>
        </div>
      </div>
    </div>

    <!-- Sidebar Footer (current user) -->
    <div class="sidebar-footer">
      <div class="sf-glow-line"></div>
      <?php $hasAvatar = $userAvatar && $userAvatar !== 'default.png'; ?>
      <div class="sf-avatar-wrap">
        <?php if ($hasAvatar): ?>
        <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="avatar avatar-sm sf-avatar">
        <?php else: ?>
        <div class="avatar-placeholder avatar-sm sf-avatar" style="background:linear-gradient(135deg,var(--accent),var(--purple));font-size:.65rem;font-weight:800">
          <?= strtoupper(substr($userName,0,2)) ?>
        </div>
        <?php endif; ?>
        <div class="sf-online-dot"></div>
      </div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($userName) ?></div>
        <div class="user-status">● Online</div>
      </div>
      <div class="sf-actions">
        <a href="<?= BASE_URL ?>/profile.php" class="sf-btn" title="My Profile">👤</a>
        <a href="<?= BASE_URL ?>/settings.php" class="sf-btn" title="Settings">⚙️</a>
        <a href="<?= BASE_URL ?>/api/auth/logout.php" class="sf-btn sf-btn-logout" title="Sign Out">🚪</a>
      </div>
    </div>

  </aside>

  <!-- ==================== MAIN CHAT ==================== -->
  <main class="chat-main">

    <!-- Chat Empty State -->
    <div class="chat-empty" id="chat-empty">
      <div class="empty-icon">💬</div>
      <h3>Select a conversation</h3>
      <p style="color:var(--text-muted);font-size:.875rem">Choose from your chats or search for a user to start messaging</p>
    </div>

    <!-- Chat Body (hidden until conversation selected) -->
    <div id="chat-body" style="display:none">

      <!-- Chat Header -->
      <div class="chat-header" id="chat-header"></div>

      <!-- Messages Area -->
      <div class="messages-area" id="messages-area"></div>

      <!-- Reply Preview -->
      <div id="reply-preview" class="reply-preview hidden">
        <div class="reply-info"></div>
        <button class="reply-cancel" onclick="Chat.clearReply()">✕</button>
      </div>

      <!-- File Preview -->
      <div id="file-preview-bar" class="file-preview-bar hidden">
        <img id="file-preview-img" src="" alt="" class="hidden">
        <span id="file-preview-name" class="file-name"></span>
        <button class="remove-file" onclick="Upload.clearFile()">✕</button>
      </div>

      <!-- Input Bar (Instagram DM style) -->
      <div id="input-bar-wrap" class="relative">
        <div class="input-bar">

          <!-- Left: Camera/Media button -->
          <button class="ib-media-btn" onclick="Upload.triggerFilePicker()" title="Send photo or video">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
              <circle cx="12" cy="13" r="4"/>
            </svg>
          </button>
          <input type="file" id="file-input" style="display:none" onchange="Upload.handleFileChange(this)"
                 accept="image/*,video/*,audio/*,.pdf,.doc,.docx">

          <!-- Center: Text input with emoji -->
          <div class="input-wrap" id="input-wrap">
            <button class="input-action-btn ib-emoji-btn" onclick="Chat.toggleEmoji()" title="Emoji">😊</button>
            <textarea id="message-input" rows="1" placeholder="Message..."></textarea>
          </div>

          <!-- Right: Action buttons (hidden when typing) -->
          <div class="ib-actions" id="ib-actions">
            <button class="ib-action-btn" title="Voice message" onclick="App._toast('Voice messages coming soon!')">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>
              </svg>
            </button>
            <button class="ib-action-btn" title="Photo gallery" onclick="Upload.triggerFilePicker()">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
              </svg>
            </button>
            <button class="ib-action-btn" title="Sticker / GIF" onclick="App._toast('Stickers coming soon!')">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>
              </svg>
            </button>
          </div>

          <!-- Send button (shown when typing) -->
          <button class="send-btn" onclick="Chat.sendMessage()" id="send-btn" title="Send" style="display:none">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
            </svg>
          </button>

        </div>
      </div>


    </div><!-- /chat-body -->

  </main>
</div>

<!-- Mobile Sidebar Toggle -->
<button class="mobile-menu-btn" id="mobile-menu-btn" onclick="App.toggleMobileSidebar()" aria-label="Open conversations">☰</button>

<!-- ==================== New Group Modal ==================== -->
<div class="modal-overlay" id="new-group-modal">
  <div class="modal">
    <h3 style="margin-bottom:20px">Create Group</h3>
    <div class="flex-col gap-3" id="group-form">
      <div class="form-group">
        <label class="form-label">Group Name</label>
        <input class="form-input" type="text" id="group-name" placeholder="e.g. Project Team">
      </div>
      <div class="form-group">
        <label class="form-label">Add Members (search)</label>
        <input class="form-input" type="text" id="group-member-search" placeholder="Search users…">
        <div id="group-member-results" style="margin-top:8px;display:flex;flex-direction:column;gap:4px;max-height:200px;overflow-y:auto"></div>
      </div>
      <div id="group-selected-members" style="display:flex;flex-wrap:wrap;gap:8px;min-height:32px"></div>
      <div class="flex gap-2" style="margin-top:8px">
        <button class="btn btn-ghost" onclick="document.getElementById('new-group-modal').classList.remove('active')">Cancel</button>
        <button class="btn btn-primary" onclick="GroupChat.createGroup()">Create Group</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script src="assets/js/chat.js"></script>
<script src="assets/js/upload.js"></script>
<script src="assets/js/notifications.js"></script>
<script src="assets/js/call.js"></script>
<script>
// Group chat creation
const GroupChat = {
  selectedMembers: {},

  init() {
    let timer;
    document.getElementById('group-member-search').addEventListener('input', function() {
      clearTimeout(timer);
      const q = this.value.trim();
      if (!q) { document.getElementById('group-member-results').innerHTML = ''; return; }
      timer = setTimeout(() => GroupChat.searchForGroup(q), 300);
    });
  },

  async searchForGroup(q) {
    const res  = await fetch(`${App.BASE_URL}/api/users/search.php?q=${encodeURIComponent(q)}`);
    const data = await res.json();
    const list = document.getElementById('group-member-results');
    list.innerHTML = (data.users || []).map(u => `
      <div class="search-result-item" onclick="GroupChat.addMember(${u.user_id},'${App.esc(u.full_name)}')">
        <div class="avatar-placeholder avatar-sm" style="background:var(--accent);font-size:.65rem">${u.full_name.split(' ').map(w=>w[0]).join('').slice(0,2)}</div>
        <div>
          <div style="font-size:.875rem;font-weight:600">${App.esc(u.full_name)}</div>
          <div style="font-size:.75rem;color:var(--text-muted)">@${App.esc(u.username)}</div>
        </div>
      </div>`).join('');
  },

  addMember(id, name) {
    if (this.selectedMembers[id]) return;
    this.selectedMembers[id] = name;
    this.renderSelected();
  },

  removeMember(id) {
    delete this.selectedMembers[id];
    this.renderSelected();
  },

  renderSelected() {
    const wrap = document.getElementById('group-selected-members');
    wrap.innerHTML = Object.entries(this.selectedMembers).map(([id, name]) => `
      <div style="display:flex;align-items:center;gap:4px;background:rgba(79,142,247,.15);border:1px solid rgba(79,142,247,.3);border-radius:99px;padding:4px 10px;font-size:.8125rem">
        ${App.esc(name)}
        <button onclick="GroupChat.removeMember(${id})" style="background:none;border:none;color:var(--text-muted);margin-left:4px;font-size:.75rem">✕</button>
      </div>`).join('');
  },

  async createGroup() {
    const name = document.getElementById('group-name').value.trim();
    const members = Object.keys(this.selectedMembers).map(Number);
    if (!name) { alert('Please enter a group name.'); return; }
    if (!members.length) { alert('Please add at least one member.'); return; }

    const params = new URLSearchParams({ type: 'group', name });
    members.forEach(m => params.append('members[]', m));
    const res  = await fetch(`${App.BASE_URL}/api/conversations/create.php`, {
      method:'POST', body: params
    });
    const data = await res.json();
    if (data.success) {
      document.getElementById('new-group-modal').classList.remove('active');
      this.selectedMembers = {};
      document.getElementById('group-name').value = '';
      await App.loadConversations();
      App.openConversation(data.conversation_id, name, null, false);
    }
  }
};

GroupChat.init();

// ---- On mobile: auto-show sidebar so users see their conversations first ----
if (window.innerWidth <= 640) {
  document.getElementById('sidebar')?.classList.add('mobile-open');
  const btn = document.getElementById('mobile-menu-btn');
  if (btn) btn.style.opacity = '0';
}

// Close modal on overlay click
document.getElementById('new-group-modal').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('active');
});

// ---- Tab switching ----
App.switchTab = function(tab) {
  const isChats = tab === 'chats';
  const chatsPanel   = document.getElementById('panel-chats');
  const friendsPanel = document.getElementById('panel-friends');
  if (chatsPanel)   { chatsPanel.style.display   = isChats  ? 'flex' : 'none'; }
  if (friendsPanel) { friendsPanel.style.display  = !isChats ? 'flex' : 'none'; friendsPanel.classList.remove('hidden'); }
  document.getElementById('tab-chats').classList.toggle('active', isChats);
  document.getElementById('tab-friends').classList.toggle('active', !isChats);
  if (!isChats) {
    App.loadFriends();
  } else {
    // Clear the Chats unread badge when user explicitly opens the Chats tab
    const chatsBadge = document.getElementById('chats-unread-badge');
    if (chatsBadge) chatsBadge.classList.add('hidden');
  }
};

// ---- People Search (Friends tab) — "Add Friend" is primary ----
(() => {
  const input   = document.getElementById('people-search');
  const results = document.getElementById('people-results');
  if (!input) return;
  let timer;
  input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (!q) { results.classList.add('hidden'); return; }
    timer = setTimeout(async () => {
      const res  = await fetch(`${App.BASE_URL}/api/users/search.php?q=${encodeURIComponent(q)}`);
      const data = await res.json();
      results.classList.remove('hidden');
      if (!data.users?.length) {
        results.innerHTML = '<div class="search-result-item"><span style="color:var(--text-muted)">No users found</span></div>';
        return;
      }
      const statuses = await Promise.all(
        data.users.map(u => fetch(`${App.BASE_URL}/api/friends/status.php?user_id=${u.user_id}`).then(r=>r.json()).catch(()=>({status:'none'})))
      );
      results.innerHTML = data.users.map((u, i) => {
        const status   = statuses[i]?.status || 'none';
        const initials = u.full_name.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
        let btn = '';
        // Chat only available AFTER friend request accepted
        if      (status === 'friends')          btn = `<button class="friend-badge friend-badge--friends" onclick="App.startChat(${u.user_id},'${App.esc(u.full_name)}')">💬 Message</button>`;
        else if (status === 'pending_sent')     btn = `<button class="friend-badge friend-badge--pending" disabled>⏳ Pending</button>`;
        else if (status === 'pending_received') btn = `<button class="friend-badge friend-badge--accept" onclick="App.respondFriend(${u.user_id},'accept',this)">✓ Accept</button>`;
        else                                    btn = `<button class="friend-badge friend-badge--add" onclick="App.addFriend(${u.user_id},this)">➕ Add Friend</button>`;
        return `<div class="search-result-item">
          <div class="avatar-placeholder avatar-sm" style="background:var(--accent);font-size:.7rem">${initials}</div>
          <div style="flex:1;min-width:0">
            <div style="font-size:.875rem;font-weight:600">${App.esc(u.full_name)}</div>
            <div style="font-size:.75rem;color:var(--text-muted)">@${App.esc(u.username)}</div>
          </div>
          ${u.is_online ? '<div class="online-dot" style="flex-shrink:0"></div>' : ''}
          ${btn}
        </div>`;
      }).join('');
    }, 300);
  });
  document.addEventListener('click', e => {
    if (!input.contains(e.target) && !results.contains(e.target)) results.classList.add('hidden');
  });
})();

// ---- loadFriends: populate friends panel with list + "My Profile" pinned at bottom ----
App.loadFriends = async function() {
  const panel = document.getElementById('friends-panel');
  if (!panel) return;
  panel.innerHTML = `<div style="display:flex;justify-content:center;padding:32px"><div class="spinner"></div></div>`;
  try {
    const res  = await fetch(`${App.BASE_URL}/api/friends/list.php`);
    const data = await res.json();
    const friends  = data.friends          || [];
    const pending  = data.pending_received  || [];
    let html = '';

    // ---- Pending requests section ----
    if (pending.length) {
      html += `<div class="friends-section-title">📨 Friend Requests (${pending.length})</div>`;
      html += pending.map(u => {
        const initials = (u.full_name||'?').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
        return `<div class="people-item">
          <div class="avatar-placeholder avatar-sm" style="background:var(--accent);font-size:.7rem;flex-shrink:0">${initials}</div>
          <div class="people-info">
            <div class="people-name">${App.esc(u.full_name)}</div>
            <div class="people-user">@${App.esc(u.username)}</div>
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0">
            <button class="friend-badge friend-badge--accept" onclick="App.respondFriend(${u.user_id},'accept',this)">✓</button>
            <button class="friend-badge friend-badge--pending" onclick="App.respondFriend(${u.user_id},'decline',this)">✕</button>
          </div>
        </div>`;
      }).join('');
    }

    // ---- Friends list ----
    if (friends.length) {
      html += `<div class="friends-section-title">👥 Friends (${friends.length})</div>`;
      html += friends.map(u => {
        const initials = (u.full_name||'?').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
        const colors = ['#4f8ef7','#8b5cf6','#22c55e','#f59e0b','#ef4444','#06b6d4'];
        const color  = colors[u.user_id % colors.length];
        return `<div class="people-item">
          <div style="position:relative;flex-shrink:0">
            <div class="avatar-placeholder avatar-sm" style="background:${color};font-size:.7rem">${initials}</div>
            ${u.is_online ? '<div class="online-dot" style="position:absolute;bottom:0;right:0;width:9px;height:9px;border-width:1.5px"></div>' : ''}
          </div>
          <div class="people-info">
            <div class="people-name">${App.esc(u.full_name)}</div>
            <div class="people-user">@${App.esc(u.username)} ${u.is_online?'· <span style="color:var(--green)">Online</span>':''}</div>
          </div>
          <button class="friend-badge friend-badge--friends" onclick="App.startChat(${u.user_id},'${App.esc(u.full_name)}')">💬</button>
        </div>`;
      }).join('');
    }

    if (!friends.length && !pending.length) {
      html += `<div style="text-align:center;padding:40px 20px;color:var(--text-muted)">
        <div style="font-size:2.5rem;margin-bottom:10px">🤝</div>
        <p style="font-weight:600;margin-bottom:4px">No friends yet</p>
        <p style="font-size:.82rem">Search for people above to send a friend request</p>
      </div>`;
    }

    // ---- "My Profile" card pinned at the very bottom ----
    const myName   = document.querySelector('.sidebar-footer .user-name')?.textContent  || 'Me';
    const myStatus = document.querySelector('.sidebar-footer .user-status')?.textContent || '● Online';
    const myInit   = myName.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
    html += `
      <div class="my-profile-card">
        <div class="my-profile-glow"></div>
        <div class="avatar-placeholder avatar-sm" style="background:linear-gradient(135deg,var(--accent),var(--purple));font-size:.7rem;flex-shrink:0">${myInit}</div>
        <div class="people-info">
          <div class="people-name">${App.esc(myName)} <span style="font-size:.72rem;color:var(--text-muted);font-weight:400">(You)</span></div>
          <div class="people-user" style="color:var(--green)">${myStatus}</div>
        </div>
        <a href="${App.BASE_URL}/profile.php" class="friend-badge friend-badge--friends" style="text-decoration:none">✏️ Edit</a>
      </div>`;

    panel.innerHTML = html;
  } catch(e) {
    panel.innerHTML = `<div style="text-align:center;padding:32px;color:var(--red)">Failed to load friends.</div>`;
  }
};

// Poll for friend requests and show badge
async function pollFriendBadge() {
  try {
    const res  = await fetch(`${App.BASE_URL}/api/friends/list.php`);
    const data = await res.json();
    const n    = data.pending_count || 0;
    const badge = document.getElementById('friends-req-badge');
    if (badge) {
      badge.textContent = n;
      badge.classList.toggle('hidden', !n);
    }
  } catch(_) {}
}
pollFriendBadge();
setInterval(pollFriendBadge, 30000);

// ---- Mobile sidebar toggle helper ----
App.toggleMobileSidebar = function() {
  const sidebar = document.getElementById('sidebar');
  const btn     = document.getElementById('mobile-menu-btn');
  if (!sidebar) return;
  const isOpen = sidebar.classList.toggle('mobile-open');
  if (btn) btn.style.opacity = isOpen ? '0' : '1';
};

// ---- Override goBackToList ----
const _origBack = App.goBackToList.bind(App);
App.goBackToList = function() {
  _origBack();
  const btn = document.getElementById('mobile-menu-btn');
  if (btn) btn.style.opacity = '1';
};

// ---- Override openConversation to show chat-body ----
const origOpen = App.openConversation.bind(App);
App.openConversation = function(...args) {
  origOpen(...args);
  const body = document.getElementById('chat-body');
  if (body) { body.style.display = 'flex'; }
  const empty = document.getElementById('chat-empty');
  if (empty) { empty.style.display = 'none'; }
  const btn = document.getElementById('mobile-menu-btn');
  if (btn) btn.style.opacity = '0';
  // On mobile, switch to chats tab automatically
  if (window.innerWidth <= 640) App.switchTab('chats');
};

</script>

<!-- ==================== Call Modal ==================== -->
<div class="call-modal-overlay" id="call-modal">
  <div class="call-modal-card">

    <!-- Video feeds (hidden for voice calls) -->
    <div class="call-video-area hidden">
      <video id="call-remote-video" autoplay playsinline class="call-remote-vid"></video>
      <video id="call-local-video"  autoplay playsinline muted class="call-local-vid"></video>
    </div>

    <!-- Avatar + info (shown when no video) -->
    <div class="call-avatar-section">
      <div class="call-avatar-ring">
        <div class="call-avatar-inner">
          <span class="call-type-icon">📞</span>
        </div>
      </div>
      <div class="call-user-name">User</div>
      <div class="call-status">Calling…</div>
      <div class="call-calling-hint hidden" style="font-size:.8rem;color:rgba(255,255,255,.4);margin-top:4px">Waiting for answer…</div>
    </div>

    <!-- Incoming call buttons -->
    <div class="call-incoming-btns hidden">
      <button class="call-btn call-reject" onclick="Call.rejectCall()" title="Decline">📵</button>
      <button class="call-btn call-accept" onclick="Call.acceptCall()" title="Accept">📞</button>
    </div>

    <!-- Active call controls -->
    <div class="call-active-btns hidden">
      <button class="call-ctrl" id="call-mute-btn"   onclick="Call.toggleMute()"    title="Mute">🎤</button>
      <button class="call-ctrl" id="call-cam-btn"    onclick="Call.toggleCamera()"  title="Camera" style="display:none">📷</button>
      <button class="call-ctrl" id="call-spk-btn"    onclick="Call.toggleSpeaker()" title="Speaker">🔊</button>
      <button class="call-ctrl call-ctrl-end"        onclick="Call.endCall()"       title="End Call">📵</button>
    </div>

    <!-- End call button shown while calling/ringing -->
    <div class="call-hangup-wrap">
      <button class="call-btn call-reject" onclick="Call.endCall()" title="Cancel" id="call-cancel-btn">📵</button>
    </div>

  </div>
</div>

<!-- ==================== Notification Slide Panel ==================== -->
<div class="notif-overlay" id="notif-overlay" onclick="App.toggleNotifications()"></div>
<aside class="notif-panel" id="notif-panel">
  <div class="notif-panel-header">
    <div class="notif-panel-title">
      <span class="notif-panel-icon">🔔</span>
      <h3>Notifications</h3>
      <span class="notif-count-pill" id="notif-count-pill">0</span>
    </div>
    <div class="notif-panel-actions">
      <button class="btn-ghost btn-sm" onclick="App.markAllNotifRead()" title="Mark all as read">✓ Read all</button>
      <button class="notif-close-btn" onclick="App.toggleNotifications()" title="Close">✕</button>
    </div>
  </div>
  <div class="notif-panel-body" id="notif-list">
    <div class="notif-empty">
      <div class="notif-empty-icon">🔕</div>
      <p>No notifications yet</p>
      <span>When you get notifications, they'll show up here</span>
    </div>
  </div>
</aside>

</body>
</html>
