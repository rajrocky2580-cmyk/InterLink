// =============================================================
// InterLink — Main App Logic (app.js)
// =============================================================

// ---- InfinityFree-compatible POST helper ----
// InfinityFree's WAF blocks Content-Type: application/json.
// This sends data as application/x-www-form-urlencoded instead.
// Arrays are sent with key[] convention so PHP reads them as arrays.
function postForm(url, data) {
  const p = new URLSearchParams();
  Object.entries(data || {}).forEach(([k, v]) => {
    if (Array.isArray(v)) v.forEach(x => p.append(k + '[]', x));
    else p.append(k, v ?? '');
  });
  return fetch(url, { method: 'POST', body: p });
}

const App = {
  BASE_URL: document.querySelector('meta[name="base-url"]')?.content || '',
  currentConvId: null,
  currentConvUserId: null,
  currentConvType: 'private',  // 'private' | 'group'
  lastMessageId: 0,
  pollInterval: null,
  notifInterval: null,
  convListInterval: null,   // polls sidebar every 3 s
  _unreadSnapshot: {},      // tracks {convId: unreadCount} for new-msg detection
  replyTo: null,
  pendingFile: null,

  init() {
    this.bindSidebarEvents();
    this.loadConversations();
    this.startNotificationPolling();
    this.startConvListPolling();   // real-time sidebar refresh
    this.requestNotifPermission(); // ask for browser push notification permission
    this._initBackButton();        // handle Android back button
    this.updateStatus('online');               // mark online immediately
    this._startHeartbeat();                    // keep last_seen fresh every 30 s
    // keepalive:true ensures the browser sends this even when the tab is closing
    window.addEventListener('beforeunload', () => {
      navigator.sendBeacon(
        `${this.BASE_URL}/api/users/status.php`,
        new URLSearchParams({ status: 'offline' })
      );
    });
    document.addEventListener('click', () => this.closeContextMenus());
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const panel = document.getElementById('notif-panel');
        if (panel?.classList.contains('open')) this.toggleNotifications();
      }
    });
  },

  // ---- Browser Back Button (Android / PWA) ----
  // Logic mirrors system settings:
  //   – Back from any sub-section (chat, modal, sidebar panel) → go to Home Page
  //   – Back while already on Home Page (nothing open) → let the browser close
  _initBackButton() {
    // Replace the initial entry so we can always intercept the *first* back press
    history.replaceState({ page: 'home' }, '', location.href);
    // Push a forward sentinel state so popstate always fires on back press
    history.pushState({ page: 'sentinel' }, '', location.href);

    window.addEventListener('popstate', (e) => {
      const state = e.state || {};

      // Determine what is currently "open" (ordered: most specific → least)
      const openModal = document.querySelector('.modal-overlay.active');
      const sidebarOpen = document.getElementById('sidebar')?.classList.contains('mobile-open');
      const chatOpen = document.getElementById('chat-body')?.style.display === 'flex';

      if (openModal) {
        // Close the topmost open modal and stay on the current section
        openModal.classList.remove('active');
        history.pushState({ page: 'sentinel' }, '', location.href);
        return;
      }

      if (sidebarOpen) {
        // Close the mobile sidebar overlay — back to home view
        document.getElementById('sidebar')?.classList.remove('mobile-open');
        history.pushState({ page: 'sentinel' }, '', location.href);
        return;
      }

      if (chatOpen) {
        // Back from inside a chat → go to Home (conversation list)
        this.goBackToList();
        history.pushState({ page: 'sentinel' }, '', location.href);
        return;
      }

      // We are already on the Home Page — allow the browser to navigate away /
      // close the app on the next back press (don't re-push so history drains)
      // Re-initialize so the next visit to any section can be intercepted again
      history.replaceState({ page: 'home' }, '', location.href);
    });
  },

  // ---- Request browser push notification permission ----
  async requestNotifPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
      await Notification.requestPermission();
    }
  },

  // ---- Show a native browser push notification ----
  _showBrowserNotif(title, body, convId) {
    if (!('Notification' in window)) return;
    if (Notification.permission !== 'granted') return;
    // Don't notify if conversation is currently open and visible
    if (this.currentConvId === convId && !document.hidden) return;
    const n = new Notification(title, {
      body,
      icon: `${this.BASE_URL}/assets/images/favicon.png`,
      badge: `${this.BASE_URL}/assets/images/favicon.png`,
      tag: `interlink-conv-${convId}`,
      renotify: true,
    });
    n.onclick = () => {
      window.focus();
      const el = document.querySelector(`.conv-item[data-id="${convId}"]`);
      if (el) el.click();
      n.close();
    };
  },

  // ---- Conversation List Polling (every 3 s) ----
  startConvListPolling() {
    clearInterval(this.convListInterval);
    this.convListInterval = setInterval(async () => {
      try {
        const res = await fetch(`${this.BASE_URL}/api/conversations/list.php`);
        const data = await res.json();
        const convs = data.conversations || [];

        // Detect newly unread conversations and fire browser notification
        convs.forEach(c => {
          const prevUnread = this._unreadSnapshot[c.conversation_id] || 0;
          const nowUnread = c.unread_count || 0;
          if (nowUnread > prevUnread && c.conversation_id !== this.currentConvId) {
            // New unread message(s) arrived
            const senderName = c.display_name || 'Someone';
            const preview = c.last_message_type === 'image' ? '📷 Photo'
              : c.last_message_type === 'file' ? '📎 File'
                : c.last_message_type === 'video' ? '🎬 Video'
                  : (c.last_message || 'New message');
            this._showBrowserNotif(`💬 ${senderName}`, preview, c.conversation_id);
            // Flash the tab title
            this._flashTitle(`New message from ${senderName}`);
          }
          this._unreadSnapshot[c.conversation_id] = nowUnread;
        });

        // Re-render sidebar with fresh data
        this.renderConversations(convs);
        if (this.currentConvId) {
          const active = document.querySelector(`.conv-item[data-id="${this.currentConvId}"]`);
          if (active) active.classList.add('active');
        }

        // Update the Chats tab badge with total unread conversations
        const totalUnread = convs.reduce((sum, c) => sum + (c.unread_count || 0), 0);
        const chatsBadge = document.getElementById('chats-unread-badge');
        if (chatsBadge) {
          // Only show badge if Chats tab is NOT currently active (same as Friends badge logic)
          const chatsTabActive = document.getElementById('tab-chats')?.classList.contains('active');
          if (totalUnread > 0 && !chatsTabActive) {
            chatsBadge.textContent = totalUnread > 99 ? '99+' : totalUnread;
            chatsBadge.classList.remove('hidden');
          } else {
            chatsBadge.classList.add('hidden');
          }
        }
      } catch (_) { }
    }, 3000);
  },

  // ---- Flash tab title when new message arrives ----
  _flashTitle(msg) {
    if (document.hidden) {
      const orig = document.title;
      let on = true;
      const t = setInterval(() => {
        document.title = on ? `🔔 ${msg}` : orig;
        on = !on;
      }, 1000);
      const stop = () => { clearInterval(t); document.title = orig; };
      document.addEventListener('visibilitychange', stop, { once: true });
      window.addEventListener('focus', stop, { once: true });
    }
  },

  // ---- Conversation List ----
  async loadConversations() {
    try {
      const res = await fetch(`${this.BASE_URL}/api/conversations/list.php`);
      const data = await res.json();
      this.renderConversations(data.conversations || []);
    } catch (e) { console.error('Failed to load conversations', e); }
  },

  renderConversations(convs) {
    const list = document.getElementById('conv-list');
    if (!list) return;
    if (!convs.length) {
      list.innerHTML = `<div class="chat-empty" style="padding:40px 20px">
        <div class="empty-icon">💬</div>
        <p style="color:var(--text-secondary);text-align:center;font-size:.875rem">No conversations yet.<br>Search for a user to start chatting!</p>
      </div>`;
      return;
    }
    list.innerHTML = convs.map(c => this.convItemHTML(c)).join('');
    list.querySelectorAll('.conv-item').forEach(el => {
      el.addEventListener('click', () => this.openConversation(
        +el.dataset.id, el.dataset.name, el.dataset.avatar,
        el.dataset.online === '1', +el.dataset.userId || null,
        el.dataset.type || 'private',
        el.dataset.lastSeen || null
      ));
    });
  },

  convItemHTML(c) {
    const isUnread = (c.unread_count || 0) > 0;
    const initials = (c.display_name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
    const colors = ['#4f8ef7', '#8b5cf6', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4'];
    const color = colors[c.conversation_id % colors.length];
    const online = c.is_online ? `<div class="online-dot"></div>` : '';
    const unreadDot = isUnread ? `<div class="conv-unread-dot"></div>` : '';
    const preview = c.last_message_type === 'image' ? '📷 Photo'
      : c.last_message_type === 'file' ? '📎 File'
        : c.last_message_type === 'video' ? '🎬 Video'
          : c.last_message_type === 'audio' ? '🎵 Audio'
            : (c.last_message ? this.esc(c.last_message) : '<em style="opacity:.5">No messages yet</em>');
    const safeName = this.esc(c.display_name || '');
    const lastSeenFmt = this.esc(c.last_seen_fmt || '');
    return `
    <div class="conv-item${isUnread ? ' unread' : ''}" data-id="${c.conversation_id}" data-name="${safeName}" data-avatar="${this.esc(c.avatar || '')}" data-online="${c.is_online || 0}" data-user-id="${c.other_user_id || 0}" data-type="${c.type || 'private'}" data-last-seen="${lastSeenFmt}">
      <div class="conv-avatar-wrap">
        <div class="avatar-placeholder avatar-md" style="background:${color}">${initials}</div>
        ${online}
      </div>
      <div class="conv-info">
        <div class="conv-name">${safeName}</div>
        <div class="conv-preview">${preview}</div>
      </div>
      <div class="conv-meta">
        <span class="conv-time">${c.sent_at || ''}</span>
        ${unreadDot}
      </div>
    </div>`;
  },

  // ---- Open Conversation ----
  openConversation(convId, name, avatar, isOnline, userId = null, convType = 'private', lastSeenFmt = null) {
    this.currentConvId = convId;
    this.currentConvUserId = userId;
    this.currentConvType = convType;
    this.currentLastSeen = lastSeenFmt;
    this.lastMessageId = 0;
    this.replyTo = null;
    clearInterval(this.pollInterval);
    clearInterval(this._statusInterval);

    // Mark active
    document.querySelectorAll('.conv-item').forEach(el => el.classList.toggle('active', +el.dataset.id === convId));

    // Build chat header with real Online / Last Seen status
    this._buildChatHeader(convId, name, avatar, isOnline, userId, lastSeenFmt);

    // Live-refresh online status every 30 s while conversation is open
    if (userId) {
      this._statusInterval = setInterval(() => this._refreshHeaderStatus(convId, userId), 30000);
    }

    // Show chat body, hide empty state
    const emptyEl = document.getElementById('chat-empty');
    if (emptyEl) emptyEl.style.display = 'none';
    const bodyEl = document.getElementById('chat-body');
    if (bodyEl) bodyEl.style.display = 'flex';

    // On mobile, hide sidebar and mark body as chat-active
    // (body.chat-active hides sidebar via CSS so conv usernames can't bleed through)
    document.getElementById('sidebar')?.classList.remove('mobile-open');
    document.body.classList.add('chat-active');

    // Load messages + start polling
    Chat.loadMessages(convId);
    this.markRead(convId);
    Chat.startReadReceiptPolling(convId);  // live grey→red tick upgrade

    // Push history state so back button returns to list, not previous page
    history.pushState({ page: 'chat', convId }, '', location.href);

    this.pollInterval = setInterval(() => {
      Chat.pollMessages(convId);
    }, 2000);
  },

  // ---- Build / Rebuild Chat Header ----
  _buildChatHeader(convId, name, avatar, isOnline, userId, lastSeenFmt) {
    const header = document.getElementById('chat-header');
    if (!header) return;
    const initials = (name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
    const colors = ['#4f8ef7', '#8b5cf6', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4'];
    const color = colors[(convId || 0) % colors.length];

    // Avatar — try real photo first, fall back to initials
    const safeAvatar = avatar && avatar !== 'null' && !avatar.endsWith('default.png');
    const avatarHtml = safeAvatar
      ? `<img src="${window.location.origin}/InterLink/uploads/avatars/${avatar.split('/').pop()}"
             alt="${this.esc(name)}"
             style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid rgba(79,142,247,0.4);"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
      : '';
    const initialsHtml = `<div style="width:38px;height:38px;border-radius:50%;background:${color};
      font-size:.85rem;font-weight:700;color:#fff;display:flex;align-items:center;justify-content:center;
      flex-shrink:0;${safeAvatar ? 'display:none' : ''}">${initials}</div>`;

    // Status text — exactly like Instagram / WhatsApp
    const statusText = isOnline
      ? '● Active now'
      : (lastSeenFmt || 'Last seen recently');
    const statusClass = isOnline ? 'online' : '';

    header.innerHTML = `
      <button class="mobile-back-btn" id="mobile-back-btn" onclick="App.goBackToList()" title="Back" aria-label="Back to conversations">‹</button>
      <div style="position:relative;flex-shrink:0;">
        ${avatarHtml}${initialsHtml}
        ${isOnline ? '<div class="online-dot" style="position:absolute;bottom:1px;right:1px;border-color:var(--bg-secondary)"></div>' : ''}
      </div>
      <div class="chat-header-info">
        <div class="chat-header-name">${this.esc(name)}</div>
        <div class="chat-header-status ${statusClass}" id="chat-header-status">${statusText}</div>
      </div>
      <div class="chat-header-actions">
        ${userId ? `
        <button class="btn-icon call-header-btn" title="Voice Call" onclick="Call.startCall(false)" id="btn-voice-call">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.65 3.44 2 2 0 0 1 3.62 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.37a16 16 0 0 0 6.72 6.72l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
          </svg>
        </button>
        <button class="btn-icon call-header-btn" title="Video Call" onclick="Call.startCall(true)" id="btn-video-call">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
          </svg>
        </button>
        ` : ''}
        <button class="btn-icon call-header-btn" title="More options" id="btn-chat-menu" onclick="App.toggleChatMenu(event)">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>
          </svg>
        </button>
      </div>`;
    setTimeout(() => this._loadWallpaper(), 0);
  },

  // ---- Refresh header status without rebuilding the entire header ----
  async _refreshHeaderStatus(convId, userId) {
    try {
      // Re-fetch conversation list to get fresh is_online + last_seen_fmt
      const res = await fetch(`${this.BASE_URL}/api/conversations/list.php`);
      const data = await res.json();
      const conv = (data.conversations || []).find(c => c.conversation_id == convId);
      if (!conv) return;
      const statusEl = document.getElementById('chat-header-status');
      if (!statusEl) return;
      if (conv.is_online) {
        statusEl.textContent = '● Active now';
        statusEl.className = 'chat-header-status online';
      } else {
        statusEl.textContent = conv.last_seen_fmt || 'Last seen recently';
        statusEl.className = 'chat-header-status';
      }
      // Also update the online dot in the header
      const dot = document.querySelector('#chat-header .online-dot');
      if (dot && !conv.is_online) dot.remove();
    } catch (_) { }
  },

  // ---- Back to list (mobile) ----
  goBackToList() {
    const bodyEl = document.getElementById('chat-body');
    if (bodyEl) bodyEl.style.display = 'none';
    const emptyEl = document.getElementById('chat-empty');
    if (emptyEl) emptyEl.style.display = '';
    document.getElementById('sidebar')?.classList.add('mobile-open');
    // Remove chat-active so sidebar is visible again
    document.body.classList.remove('chat-active');
    this.currentConvId = null;
    clearInterval(this.pollInterval);
    clearInterval(this._statusInterval);
    Chat.stopReadReceiptPolling();  // stop tick upgrade poller
    document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
    // Remove any leftover new-msg banner
    document.querySelector('.new-msg-banner')?.remove();
  },

  async markRead(convId) {
    // Instantly remove unread styling (don't wait for next poll cycle)
    const el = document.querySelector(`.conv-item[data-id="${convId}"]`);
    if (el) {
      el.classList.remove('unread');
      el.querySelector('.conv-unread-dot')?.remove();
    }
    // Also reset snapshot so we don't re-fire notification for same messages
    if (this._unreadSnapshot[convId]) this._unreadSnapshot[convId] = 0;

    await postForm(`${this.BASE_URL}/api/messages/read.php`, { conversation_id: convId });
  },

  // ---- User Search ----
  bindSidebarEvents() {
    const searchInput = document.getElementById('user-search');
    const searchResults = document.getElementById('search-results');
    if (!searchInput) return;

    let searchTimer;
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);
      const q = searchInput.value.trim();
      if (!q) { searchResults?.classList.add('hidden'); return; }
      searchTimer = setTimeout(() => this.searchUsers(q), 300);
    });

    searchInput.addEventListener('focus', () => {
      if (searchInput.value.trim()) searchResults?.classList.remove('hidden');
    });
    document.addEventListener('click', e => {
      if (!searchInput.contains(e.target) && !searchResults?.contains(e.target)) {
        searchResults?.classList.add('hidden');
      }
    });
  },

  async searchUsers(q) {
    const res = await fetch(`${this.BASE_URL}/api/users/search.php?q=${encodeURIComponent(q)}`);
    const data = await res.json();
    const list = document.getElementById('search-results');
    if (!list) return;
    list.classList.remove('hidden');
    if (!data.users?.length) {
      list.innerHTML = `<div class="search-result-item"><span style="color:var(--text-muted)">No users found</span></div>`;
      return;
    }
    // Fetch friend statuses in parallel
    const statuses = await Promise.all(
      data.users.map(u => fetch(`${this.BASE_URL}/api/friends/status.php?user_id=${u.user_id}`).then(r => r.json()).catch(() => ({ status: 'none' })))
    );
    list.innerHTML = data.users.map((u, i) => {
      const status = statuses[i]?.status || 'none';
      const initials = (u.full_name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
      const colors = ['#4f8ef7', '#8b5cf6', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4'];
      const color = colors[u.user_id % colors.length];
      let actionBtn = '';
      if (status === 'self') {
        actionBtn = `<span class="friend-badge" style="background:rgba(255,255,255,0.05);color:var(--text-muted);cursor:default">You</span>`;
      } else if (status === 'friends') {
        actionBtn = `<button class="friend-badge friend-badge--friends" onclick="App.startChat(${u.user_id},'${this.esc(u.full_name)}')">💬 Message</button>`;
      } else if (status === 'pending_sent') {
        actionBtn = `<button class="friend-badge friend-badge--pending" disabled>⏳ Pending</button>`;
      } else if (status === 'pending_received') {
        actionBtn = `<button class="friend-badge friend-badge--accept" onclick="App.respondFriendSearch(${u.user_id},'${this.esc(u.full_name)}','accept',this)">✓ Accept</button>`;
      } else {
        actionBtn = `<button class="friend-badge friend-badge--add" onclick="App.addFriend(${u.user_id},this,'${this.esc(u.full_name)}')">+ Add Friend</button>`;
      }
      return `
        <div class="search-result-item">
          <div class="avatar-placeholder avatar-sm" style="background:${color};font-size:.7rem;flex-shrink:0">${initials}</div>
          <div style="flex:1;min-width:0">
            <div style="font-size:.875rem;font-weight:600;color:var(--text-primary)">${this.esc(u.full_name)}</div>
            <div style="font-size:.75rem;color:var(--text-muted)">@${this.esc(u.username)}${u.is_online ? ' · <span style="color:#22c55e">Online</span>' : ''}</div>
          </div>
          ${actionBtn}
        </div>`;
    }).join('');
  },

  async addFriend(userId, btn, name) {
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Sending…'; }
    try {
      const res = await postForm(`${this.BASE_URL}/api/friends/request.php`, { to_user_id: userId });
      const data = await res.json();
      if (data.success) {
        if (btn) {
          if (data.status === 'accepted') {
            // They had already sent us a request — auto-accepted, open chat!
            btn.textContent = '💬 Message';
            btn.className = 'friend-badge friend-badge--friends';
            btn.onclick = () => App.startChat(userId, name || 'User');
            this._toast('🤝 ' + data.message);
            setTimeout(() => App.startChat(userId, name || 'User'), 600);
          } else {
            btn.textContent = '⏳ Pending';
            btn.className = 'friend-badge friend-badge--pending';
            btn.disabled = true;
            this._toast('🤝 ' + data.message);
          }
        }
      } else {
        if (btn) { btn.disabled = false; btn.textContent = '+ Add Friend'; }
        this._toast('⚠️ ' + (data.error || 'Failed to send request'));
      }
    } catch (e) {
      if (btn) { btn.disabled = false; btn.textContent = '+ Add Friend'; }
      this._toast('⚠️ Connection error. Please try again.');
    }
  },

  // respondFriendSearch: used inside search results (knows the name for auto-chat)
  async respondFriendSearch(fromUserId, name, action, btn) {
    if (btn) btn.disabled = true;
    try {
      const res = await postForm(`${this.BASE_URL}/api/friends/respond.php`, { from_user_id: fromUserId, action });
      const data = await res.json();
      if (data.success) {
        if (action === 'accept') {
          this._toast('✅ Friend accepted! Opening chat…');
          // Update the button to Message
          if (btn) {
            btn.textContent = '💬 Message';
            btn.className = 'friend-badge friend-badge--friends';
            btn.disabled = false;
            btn.onclick = () => App.startChat(fromUserId, name);
          }
          // Auto-open the chat after a short delay
          setTimeout(() => App.startChat(fromUserId, name), 700);
        } else {
          this._toast('❌ Request rejected');
          if (btn) btn.closest('.search-result-item')?.remove();
        }
      } else {
        if (btn) btn.disabled = false;
        this._toast('⚠️ ' + (data.error || 'Failed'));
      }
    } catch (e) {
      if (btn) btn.disabled = false;
      this._toast('⚠️ Connection error. Please try again.');
    }
  },

  // respondFriend: used in the friends panel (accept/reject without auto-opening chat)
  async respondFriend(fromUserId, action, rowEl) {
    if (rowEl) rowEl.style.opacity = '0.5';
    try {
      const res = await postForm(`${this.BASE_URL}/api/friends/respond.php`, { from_user_id: fromUserId, action });
      const data = await res.json();
      if (data.success) {
        this._toast(action === 'accept' ? '✅ Friend accepted!' : '❌ Request rejected');
        if (rowEl) rowEl.remove();
        // Reload friends panel
        const panel = document.getElementById('friends-panel');
        if (panel && !panel.classList.contains('hidden')) this.loadFriends();
      } else {
        if (rowEl) rowEl.style.opacity = '1';
        this._toast('⚠️ ' + (data.error || 'Failed'));
      }
    } catch (e) {
      if (rowEl) rowEl.style.opacity = '1';
      this._toast('⚠️ Connection error. Please try again.');
    }
  },

  async loadFriends() {
    const panel = document.getElementById('friends-panel');
    if (!panel) return;
    panel.innerHTML = '<div style="text-align:center;padding:24px"><div class="spinner"></div></div>';
    try {
      const res = await fetch(`${this.BASE_URL}/api/friends/list.php`);
      const data = await res.json();
      let html = '';

      // Pending received
      if (data.pending_received?.length) {
        html += `<div class="friends-section-title">👋 Friend Requests (${data.pending_received.length})</div>`;
        html += data.pending_received.map(u => `
          <div class="people-item">
            <div class="people-avatar">${u.full_name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()}</div>
            <div class="people-info">
              <div class="people-name">${this.esc(u.full_name)}</div>
              <div class="people-user">@${this.esc(u.username)} · ${u.time_fmt}</div>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0">
              <button class="friend-badge friend-badge--accept" onclick="App.respondFriend(${u.user_id},'accept',this.parentNode.parentNode)">✓</button>
              <button class="friend-badge friend-badge--reject" onclick="App.respondFriend(${u.user_id},'reject',this.parentNode.parentNode)">✕</button>
            </div>
          </div>`).join('');
      }

      // Friends list
      if (data.friends?.length) {
        html += `<div class="friends-section-title">👥 Friends (${data.friends.length})</div>`;
        html += data.friends.map(u => `
          <div class="people-item" onclick="App.startChat(${u.user_id},'${this.esc(u.full_name)}')" style="cursor:pointer">
            <div class="people-avatar" style="position:relative">
              ${u.full_name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()}
              ${u.is_online ? '<div class="online-dot" style="position:absolute;bottom:0;right:0"></div>' : ''}
            </div>
            <div class="people-info">
              <div class="people-name">${this.esc(u.full_name)}</div>
              <div class="people-user">@${this.esc(u.username)} · ${u.is_online ? '<span style="color:var(--green)">Online</span>' : u.last_seen_fmt}</div>
            </div>
            <button class="friend-badge friend-badge--friends" style="pointer-events:none">💬</button>
          </div>`).join('');
      }

      if (!html) {
        html = `<div style="text-align:center;padding:48px 20px;color:var(--text-muted)">
          <div style="font-size:3rem;margin-bottom:12px">🤝</div>
          <p style="font-weight:600;color:var(--text-secondary)">No friends yet</p>
          <p style="font-size:.8125rem">Search for people and send friend requests!</p>
        </div>`;
      }
      panel.innerHTML = html;
    } catch (e) {
      panel.innerHTML = '<div style="padding:20px;color:var(--red)">Failed to load friends</div>';
    }
  },

  _toast(msg) {
    const t = document.createElement('div');
    t.className = 'app-toast';
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(() => t.classList.add('app-toast--show'));
    setTimeout(() => { t.classList.remove('app-toast--show'); setTimeout(() => t.remove(), 300); }, 3500);
  },

  async startChat(userId, name) {
    document.getElementById('search-results')?.classList.add('hidden');
    document.getElementById('user-search').value = '';
    const res = await postForm(`${this.BASE_URL}/api/conversations/create.php`, { type: 'private', members: [userId] });
    const data = await res.json();
    if (data.success) {
      await this.loadConversations();
      this.openConversation(data.conversation_id, name, null, false);
    }
  },

  // ---- New Group ----
  showNewGroupModal() {
    document.getElementById('new-group-modal')?.classList.add('active');
  },

  // ---- Notifications ----
  startNotificationPolling() {
    this.fetchNotifications();
    this.notifInterval = setInterval(() => this.fetchNotifications(), 15000); // 15 s for faster badge
  },

  async fetchNotifications() {
    try {
      const res = await fetch(`${this.BASE_URL}/api/notifications/fetch.php`);
      const data = await res.json();
      const unread = data.unread_count || 0;
      const badge = document.getElementById('notif-badge');
      if (badge) {
        badge.textContent = unread > 99 ? '99+' : unread;
        badge.classList.toggle('hidden', !unread);
      }
      this.renderNotifDropdown(data.notifications || []);
    } catch (e) { }
  },

  renderNotifDropdown(notifs) {
    const list = document.getElementById('notif-list');
    if (!list) return;

    // Update count pill
    const pill = document.getElementById('notif-count-pill');
    const unreadCount = notifs.filter(n => !n.is_read).length;
    if (pill) {
      pill.textContent = unreadCount;
      if (unreadCount === 0) {
        pill.classList.add('zero');
      } else {
        pill.classList.remove('zero');
      }
    }

    if (!notifs.length) {
      list.innerHTML = `
        <div class="notif-empty">
          <div class="notif-empty-icon">🔕</div>
          <p>No notifications yet</p>
          <span>When you get notifications, they'll show up here</span>
        </div>`;
      return;
    }

    list.innerHTML = notifs.slice(0, 20).map(n => {
      const msg = this.esc(n.message || '');
      const isMsg = msg.toLowerCase().includes('message');
      const isGroup = msg.toLowerCase().includes('group');
      const isCall = msg.toLowerCase().includes('call');
      const iconClass = isCall ? 'call' : isGroup ? 'group' : isMsg ? 'message' : 'system';
      const icon = isCall ? '📞' : isGroup ? '👥' : isMsg ? '💬' : '🔔';
      const unread = !n.is_read ? 'unread' : '';
      const richMsg = msg.replace(/^(\w[\w\s]*?)(\ssent\s|\smessage\s)/i, '<strong>$1</strong>$2');
      return `
        <div class="notif-item ${unread}" onclick="App.openConvFromNotif(${n.reference_id})">
          <div class="notif-item-icon ${iconClass}">${icon}</div>
          <div class="notif-item-content">
            <div class="notif-item-text">${richMsg}</div>
            <div class="notif-item-time">${this.esc(n.time || '')}</div>
          </div>
        </div>`;
    }).join('');
  },

  toggleNotifications() {
    const panel = document.getElementById('notif-panel');
    const overlay = document.getElementById('notif-overlay');
    if (!panel) return;
    const isOpen = panel.classList.contains('open');
    panel.classList.toggle('open');
    overlay?.classList.toggle('active');
    // Prevent body scroll when panel is open on mobile
    document.body.classList.toggle('notif-panel-open', !isOpen);
    if (!isOpen) {
      // Mark as read after a short delay
      setTimeout(() => {
        fetch(`${this.BASE_URL}/api/notifications/mark_read.php`, { method: 'POST', body: new URLSearchParams() });
        document.getElementById('notif-badge')?.classList.add('hidden');
      }, 800);
    }
  },

  markAllNotifRead() {
    fetch(`${this.BASE_URL}/api/notifications/mark_read.php`, { method: 'POST', body: new URLSearchParams() });
    document.getElementById('notif-badge')?.classList.add('hidden');
    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
    const pill = document.getElementById('notif-count-pill');
    if (pill) { pill.textContent = '0'; pill.classList.add('zero'); }
  },

  openConvFromNotif(convId) {
    // Close the notification panel
    this.toggleNotifications();
    // Find conversation in list and open
    const el = document.querySelector(`.conv-item[data-id="${convId}"]`);
    if (el) {
      el.click();
    } else {
      // Reload conversations then try again
      this.loadConversations().then(() => {
        const el2 = document.querySelector(`.conv-item[data-id="${convId}"]`);
        if (el2) el2.click();
      });
    }
  },

  // ---- Heartbeat — keeps last_seen fresh so the 2-minute online window works ----
  _startHeartbeat() {
    clearInterval(this._heartbeatInterval);
    // Ping every 30 s while the tab is visible
    this._heartbeatInterval = setInterval(() => {
      if (!document.hidden) this.updateStatus('online');
    }, 30000);
    // When user returns to tab, ping immediately
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) this.updateStatus('online');
    });
  },

  // ---- Status ----
  async updateStatus(s) {
    await postForm(`${this.BASE_URL}/api/users/status.php`, { status: s });
  },

  closeContextMenus() {
    document.querySelectorAll('.msg-context-menu').forEach(m => m.remove());
  },

  esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  },

  // ---- Toast notification (brief slide-up, like WhatsApp) ----
  _toast(msg, duration = 2800) {
    document.getElementById('app-toast')?.remove();
    const el = document.createElement('div');
    el.id = 'app-toast';
    el.className = 'app-toast';
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(() => el.classList.add('app-toast--show'));
    setTimeout(() => {
      el.classList.remove('app-toast--show');
      setTimeout(() => el.remove(), 350);
    }, duration);
  },

  toggleSearch() { },

  // =========================================================
  // THREE-DOT CHAT MENU — event delegation, no inline onclick
  // =========================================================
  toggleChatMenu(e) {
    e.stopPropagation();
    document.getElementById('chat-options-menu')?.remove();
    const isMuted = !!localStorage.getItem(`muted_${this.currentConvId}`);
    const menu = document.createElement('div');
    menu.id = 'chat-options-menu';
    menu.className = 'chat-options-menu';
    menu.innerHTML = [
      { a: 'profile', icon: '👤', label: 'View Contact' },
      { a: 'mute', icon: isMuted ? '🔔' : '🔕', label: isMuted ? 'Unmute Notifications' : 'Mute Notifications' },
      { a: 'wallpaper', icon: '🎨', label: 'Chat Wallpaper' },
      { a: 'settings', icon: '⚙️', label: 'App Settings' },
      { divider: true },
      { a: 'clear', icon: '🗑️', label: 'Clear Chat' },
      { a: 'block', icon: '🚫', label: 'Block Contact', danger: true },
      { a: 'report', icon: '⚠️', label: 'Report', danger: true },
    ].map(i => i.divider
      ? '<div class="cmo-divider"></div>'
      : `<div class="cmo-item${i.danger ? ' cmo-danger' : ''}" data-action="${i.a}"><span class="cmo-icon">${i.icon}</span>${i.label}</div>`
    ).join('');

    // Event delegation — fires before document listener removes menu
    menu.addEventListener('click', ev => {
      const item = ev.target.closest('[data-action]');
      if (!item) return;
      ev.stopPropagation();
      const act = item.dataset.action;
      menu.remove();
      if (act === 'profile') this.viewContactProfile();
      else if (act === 'mute') this.muteConversation();
      else if (act === 'wallpaper') this.changeWallpaper();
      else if (act === 'settings') this.openSettings();
      else if (act === 'clear') this.clearChat();
      else if (act === 'block') this.blockUser();
      else if (act === 'report') this.reportCurrentUser();
    });

    const btn = document.getElementById('btn-chat-menu');
    if (btn) {
      const r = btn.getBoundingClientRect();
      menu.style.top = (r.bottom + 6) + 'px';
      menu.style.right = (window.innerWidth - r.right) + 'px';
    }
    document.body.appendChild(menu);
    setTimeout(() => document.addEventListener('click', () => menu.remove(), { once: true }), 0);
  },

  // ---- Wallpaper picker — data-* attributes, no inline onclick ----
  changeWallpaper() {
    document.getElementById('chat-options-menu')?.remove();
    const WALLS = [
      // ---- Gradients ----
      { id: 'default', label: 'Default', photo: false, bg: '', thumb: '#0a0e1a' },
      { id: 'midnight', label: 'Midnight', photo: false, bg: 'linear-gradient(160deg,#0a0e1a,#1a1f35)', thumb: '#1a1f35' },
      { id: 'ocean-g', label: 'Deep Sea', photo: false, bg: 'linear-gradient(160deg,#0a192f,#0a3d62)', thumb: '#0a3d62' },
      { id: 'indigo', label: 'Indigo', photo: false, bg: 'linear-gradient(160deg,#0f0c29,#302b63)', thumb: '#302b63' },
      { id: 'rose', label: 'Rose', photo: false, bg: 'linear-gradient(160deg,#1a0a10,#2d0b1a)', thumb: '#2d0b1a' },
      // ---- Nature & Wildlife photos ----
      { id: 'mountains', label: 'Mountains', photo: true, bg: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1200&q=70', thumb: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=200&q=60' },
      { id: 'forest', label: 'Forest', photo: true, bg: 'https://images.unsplash.com/photo-1448375240586-882707db888b?w=1200&q=70', thumb: 'https://images.unsplash.com/photo-1448375240586-882707db888b?w=200&q=60' },
      { id: 'ocean-p', label: 'Ocean', photo: true, bg: 'https://images.unsplash.com/photo-1505118380757-91f5f5632de0?w=1200&q=70', thumb: 'https://images.unsplash.com/photo-1505118380757-91f5f5632de0?w=200&q=60' },
      { id: 'lion', label: 'Lion', photo: true, bg: 'https://images.unsplash.com/photo-1546182990-dffeafbe841d?w=1200&q=70', thumb: 'https://images.unsplash.com/photo-1546182990-dffeafbe841d?w=200&q=60' },
      { id: 'deer', label: 'Deer', photo: true, bg: 'https://images.unsplash.com/photo-1518020382113-a7e8fc38eac9?w=1200&q=70', thumb: 'https://images.unsplash.com/photo-1518020382113-a7e8fc38eac9?w=200&q=60' },
      { id: 'blossom', label: 'Blossom', photo: true, bg: 'https://images.unsplash.com/photo-1522383225653-ad343dce6f59?w=1200&q=70', thumb: 'https://images.unsplash.com/photo-1522383225653-ad343dce6f59?w=200&q=60' },
      { id: 'aurora', label: 'Aurora', photo: true, bg: 'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?w=1200&q=70', thumb: 'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?w=200&q=60' },
      { id: 'night-sky', label: 'Night Sky', photo: true, bg: 'https://images.unsplash.com/photo-1444703686981-a3abbc4d4fe3?w=1200&q=70', thumb: 'https://images.unsplash.com/photo-1444703686981-a3abbc4d4fe3?w=200&q=60' },
      { id: 'desert', label: 'Desert', photo: true, bg: 'https://images.unsplash.com/photo-1509316785289-025f5b846b35?w=1200&q=70', thumb: 'https://images.unsplash.com/photo-1509316785289-025f5b846b35?w=200&q=60' },
      { id: 'waterfall', label: 'Waterfall', photo: true, bg: 'https://images.unsplash.com/photo-1432405972618-c60b0225b8f9?w=1200&q=70', thumb: 'https://images.unsplash.com/photo-1432405972618-c60b0225b8f9?w=200&q=60' },
    ];

    const current = localStorage.getItem(`wallpaper_${this.currentConvId}`) || 'default';
    const modal = document.createElement('div');
    modal.id = 'wallpaper-modal';
    modal.className = 'wallpaper-modal-overlay';

    const swatchHtml = (list) => list.map(w => {
      const thumbStyle = w.photo
        ? `background-image:url('${w.thumb}');background-size:cover;background-position:center`
        : `background:${w.thumb}`;
      return `<div class="wallpaper-swatch${current === w.id ? ' wp-selected' : ''}" data-wid="${w.id}" style="${thumbStyle}" title="${w.label}">
        <span class="wp-label">${w.label}</span>
        ${current === w.id ? '<span class="wp-check">✓</span>' : ''}
      </div>`;
    }).join('');

    modal.innerHTML = `
      <div class="wallpaper-modal">
        <div class="wallpaper-modal-hdr">
          <h3>Chat Wallpaper</h3>
          <button id="wp-close-btn">✕</button>
        </div>
        <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:12px">Choose a background for this conversation</p>
        <div class="wallpaper-section-label">Gradients</div>
        <div class="wallpaper-grid">${swatchHtml(WALLS.filter(w => !w.photo))}</div>
        <div class="wallpaper-section-label" style="margin-top:14px">🌿 Nature &amp; Wildlife</div>
        <div class="wallpaper-grid">${swatchHtml(WALLS.filter(w => w.photo))}</div>
      </div>`;

    modal.querySelector('#wp-close-btn').addEventListener('click', () => modal.remove());
    modal.addEventListener('click', ev => { if (ev.target === modal) modal.remove(); });

    // Swatch clicks via data-wid — no inline onclick = no quote issues
    modal.querySelectorAll('.wallpaper-swatch').forEach(el => {
      el.addEventListener('click', () => {
        const wall = WALLS.find(w => w.id === el.dataset.wid);
        if (wall) this._applyWallpaper(wall.id, wall.bg, wall.photo);
      });
    });
    document.body.appendChild(modal);
  },

  _applyWallpaper(id, bg, isPhoto = false) {
    const area = document.getElementById('messages-area');
    localStorage.setItem(`wallpaper_${this.currentConvId}`, id);
    localStorage.setItem(`wallpaper_bg_${this.currentConvId}`, bg);
    localStorage.setItem(`wallpaper_photo_${this.currentConvId}`, isPhoto ? '1' : '');
    if (area) {
      if (!bg) { area.style.backgroundImage = ''; area.style.backgroundSize = ''; }
      else if (isPhoto) { area.style.backgroundImage = `url('${bg}')`; area.style.backgroundSize = 'cover'; area.style.backgroundPosition = 'center'; }
      else { area.style.backgroundImage = bg; area.style.backgroundSize = 'cover'; }
    }
    document.getElementById('wallpaper-modal')?.remove();
    this._toast('🎨 Wallpaper applied!');
  },

  _loadWallpaper() {
    const bg = localStorage.getItem(`wallpaper_bg_${this.currentConvId}`) || '';
    const isPhoto = !!localStorage.getItem(`wallpaper_photo_${this.currentConvId}`);
    const area = document.getElementById('messages-area');
    if (!area) return;
    if (!bg) { area.style.backgroundImage = ''; area.style.backgroundSize = ''; }
    else if (isPhoto) { area.style.backgroundImage = `url('${bg}')`; area.style.backgroundSize = 'cover'; area.style.backgroundPosition = 'center'; }
    else { area.style.backgroundImage = bg; area.style.backgroundSize = 'cover'; }
  },

  viewContactProfile() {
    if (this.currentConvUserId)
      window.open(`${this.BASE_URL}/profile.php?user_id=${this.currentConvUserId}`, '_blank');
  },

  muteConversation() {
    const key = `muted_${this.currentConvId}`;
    if (localStorage.getItem(key)) {
      localStorage.removeItem(key);
      this._toast('🔔 Notifications unmuted');
    } else {
      localStorage.setItem(key, '1');
      this._toast('🔕 Notifications muted');
    }
  },

  openSettings() {
    window.location.href = `${this.BASE_URL}/settings.php`;
  },

  // ---- Clear chat ----
  async clearChat() {
    document.getElementById('chat-options-menu')?.remove();
    if (!this.currentConvId) return;
    if (!confirm('Clear all messages in this conversation? This cannot be undone.')) return;
    try {
      const res = await postForm(`${this.BASE_URL}/api/messages/clear_chat.php`, { conversation_id: this.currentConvId });
      const data = await res.json();
      if (data.success) {
        const area = document.getElementById('messages-area');
        if (area) area.innerHTML = `<div class="chat-empty"><div class="empty-icon">👋</div><p>Chat cleared. Say hello!</p></div>`;
        this._toast('🗑️ Chat cleared');
        this.loadConversations();
      } else { this._toast('⚠️ ' + (data.error || 'Failed to clear chat')); }
    } catch (e) { this._toast('⚠️ Connection error'); }
  },

  // ---- Block user ----
  async blockUser() {
    document.getElementById('chat-options-menu')?.remove();
    const userId = this.currentConvUserId;
    if (!userId) return;
    const name = document.querySelector('.chat-header-name')?.textContent || 'this user';
    if (!confirm(`Block ${name}? They won't be able to message you.`)) return;
    try {
      const res = await postForm(`${this.BASE_URL}/api/users/block.php`, { user_id: userId, action: 'block' });
      const data = await res.json();
      if (data.success) {
        this._toast(`🚫 ${name} has been blocked`);
        this.goBackToList();
      } else { this._toast('⚠️ ' + (data.error || 'Could not block user')); }
    } catch (e) { this._toast('⚠️ Connection error'); }
  },

  // ---- Report user ----
  async reportCurrentUser() {
    document.getElementById('chat-options-menu')?.remove();
    const userId = this.currentConvUserId;
    if (!userId) return;
    const reason = prompt('Why are you reporting this user?');
    if (!reason?.trim()) return;
    try {
      const res = await postForm(`${this.BASE_URL}/api/reports/submit.php`, { reported_user: userId, reason: reason.trim() });
      const data = await res.json();
      this._toast(data.success ? '✅ Report submitted. Thank you.' : '⚠️ ' + (data.error || 'Failed'));
    } catch (e) { this._toast('⚠️ Connection error'); }
  },
};

window.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('conv-list')) App.init();
});
