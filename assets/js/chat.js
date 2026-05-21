// =============================================================
// InterLink — Chat Module (chat.js)
// =============================================================

const Chat = {
  BASE_URL: document.querySelector('meta[name="base-url"]')?.content || '',

  EMOJIS: ['😀', '😂', '😍', '🥰', '😎', '🤔', '😢', '😡', '👍', '👎', '❤️', '🔥',
    '🎉', '🙏', '😭', '🤣', '😅', '😊', '🤩', '😴', '🤯', '👀', '💯', '✨',
    '🎊', '🎁', '🚀', '💪', '🙌', '👏', '🤝', '✅', '❌', '⚡', '💡', '🌟'],

  // ---- Load Messages ----
  async loadMessages(convId) {
    const area = document.getElementById('messages-area');
    if (!area) return;
    area.innerHTML = `<div style="display:flex;justify-content:center;padding:40px"><div class="spinner"></div></div>`;

    try {
      const res = await fetch(`${this.BASE_URL}/api/messages/fetch.php?conversation_id=${convId}&limit=50`);
      const data = await res.json();
      const msgs = data.messages || [];

      area.innerHTML = '';
      if (!msgs.length) {
        area.innerHTML = `<div class="chat-empty"><div class="empty-icon">👋</div><p>Say hello! Start the conversation.</p></div>`;
        return;
      }

      let lastDate = '';
      msgs.forEach(msg => {
        const msgDate = new Date(msg.sent_at).toDateString();
        if (msgDate !== lastDate) {
          area.appendChild(this.makeDateDivider(msg.sent_at));
          lastDate = msgDate;
        }
        area.appendChild(this.makeBubble(msg));
        App.lastMessageId = Math.max(App.lastMessageId, msg.message_id);
      });

      this.scrollToBottom(area);
    } catch (e) {
      area.innerHTML = `<div class="chat-empty"><p style="color:var(--red)">Failed to load messages.</p></div>`;
    }
  },

  // ---- Long Polling ----
  async pollMessages(convId) {
    if (!App.lastMessageId && App.lastMessageId !== 0) return;
    try {
      const res = await fetch(`${this.BASE_URL}/api/messages/fetch.php?conversation_id=${convId}&after=${App.lastMessageId}`);
      const data = await res.json();
      const msgs = data.messages || [];
      if (!msgs.length) return;

      const area = document.getElementById('messages-area');
      if (!area) return;
      const isNearBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 120;
      area.querySelector('.chat-empty')?.remove();

      // Count new messages from others (not from me)
      const newFromOthers = msgs.filter(m => !m.is_mine);

      msgs.forEach(msg => {
        area.appendChild(this.makeBubble(msg));
        App.lastMessageId = Math.max(App.lastMessageId, msg.message_id);
      });

      if (isNearBottom) {
        this.scrollToBottom(area);
      } else if (newFromOthers.length > 0) {
        // Show "↓ N new messages" banner at the top of the visible area
        this._showNewMsgBanner(area, newFromOthers.length);
      }

      App.markRead(convId);
      App.loadConversations();

      // Browser notification if page hidden
      if (document.hidden && Notification.permission === 'granted') {
        const last = msgs[msgs.length - 1];
        if (!last.is_mine) {
          new Notification(last.sender_name, { body: last.content, icon: last.sender_avatar });
        }
      }
    } catch (e) { }
  },

  // ---- "N new messages" banner (WhatsApp / Instagram style) ----
  _showNewMsgBanner(area, count) {
    // Remove any existing banner first
    area.parentElement?.querySelector('.new-msg-banner')?.remove();
    const banner = document.createElement('div');
    banner.className = 'new-msg-banner';
    banner.innerHTML = `<span class="new-msg-arrow">↓</span> ${count} new message${count > 1 ? 's' : ''}`;
    banner.addEventListener('click', () => {
      this.scrollToBottom(area);
      banner.remove();
    });
    // Insert banner just above the messages area (inside chat-body)
    area.parentElement?.insertBefore(banner, area);
    // Auto-dismiss after 6 seconds
    setTimeout(() => banner.remove(), 6000);
  },

  // ---- Poll read receipts: upgrade grey ticks → red ticks ----
  async pollReadReceipts(convId) {
    try {
      const res = await fetch(`${this.BASE_URL}/api/messages/read_receipts.php?conversation_id=${convId}`);
      const data = await res.json();
      const readSet = new Set((data.read_message_ids || []).map(Number));
      // Update DOM ticks for sent messages that have now been read
      document.querySelectorAll('.message.sent[data-id]').forEach(el => {
        const mid = parseInt(el.dataset.id, 10);
        if (!mid || isNaN(mid)) return;  // skip temp messages
        const tick = el.querySelector('.msg-tick');
        if (tick && readSet.has(mid) && !tick.classList.contains('read')) {
          tick.classList.add('read');
          tick.textContent = '✓✓';
          tick.title = 'Seen';
        }
      });
    } catch (e) { }
  },

  // ---- Start read-receipt polling while a conversation is open ----
  startReadReceiptPolling(convId) {
    clearInterval(this._receiptInterval);
    this._receiptInterval = setInterval(() => this.pollReadReceipts(convId), 5000);
    // Also poll immediately
    this.pollReadReceipts(convId);
  },

  // ---- Stop read-receipt polling ----
  stopReadReceiptPolling() {
    clearInterval(this._receiptInterval);
    this._receiptInterval = null;
  },

  // ---- Send Message ----
  async sendMessage() {
    const input = document.getElementById('message-input');
    const convId = App.currentConvId;
    if (!input || !convId) return;

    let content = input.value.trim();

    // Handle file upload first
    if (App.pendingFile) {
      await this.uploadAndSend(convId);
      return;
    }

    if (!content) return;

    input.value = '';
    input.style.height = 'auto';

    const payload = { conversation_id: convId, content };
    if (App.replyTo) { payload.reply_to = App.replyTo.id; }

    // Optimistic render
    const tempMsg = {
      message_id: 'temp_' + Date.now(), sender_id: 0, sender_name: 'You',
      content, message_type: 'text', sent_at: new Date().toISOString(),
      is_mine: true, time: this.nowTime(), is_edited: 0, is_deleted: 0,
      reply_to: App.replyTo?.id, reply_content: App.replyTo?.content, reply_sender: App.replyTo?.sender
    };
    const area = document.getElementById('messages-area');
    if (area) {
      const empty = area.querySelector('.chat-empty');
      if (empty) empty.remove();
      area.appendChild(this.makeBubble(tempMsg));
      this.scrollToBottom(area);
    }
    this.clearReply();

    try {
      const res = await postForm(`${this.BASE_URL}/api/messages/send.php`, payload);
      const data = await res.json();
      if (data.success) {
        App.lastMessageId = Math.max(App.lastMessageId, data.message.message_id);
        App.loadConversations();
      }
    } catch (e) { console.error('Send failed', e); }
  },

  async uploadAndSend(convId) {
    const formData = new FormData();
    formData.append('file', App.pendingFile);
    try {
      const res = await fetch(`${this.BASE_URL}/api/files/upload.php`, { method: 'POST', body: formData });
      const data = await res.json();
      if (!data.success) { alert('Upload failed: ' + (data.error || 'Unknown error')); return; }

      // Determine message type from server flags
      let msgType = 'file';
      if (data.is_image) msgType = 'image';
      else if (data.is_video) msgType = 'video';
      else if (data.is_audio) msgType = 'audio';

      const msgRes = await postForm(`${this.BASE_URL}/api/messages/send.php`, {
        conversation_id: convId,
        content: data.url,
        message_type: msgType,
        file_name: data.file_name
      });
      const msgData = await msgRes.json();
      if (msgData.success) {
        const area = document.getElementById('messages-area');
        area?.appendChild(this.makeBubble({ ...msgData.message, is_mine: true }));
        this.scrollToBottom(area);
        App.lastMessageId = Math.max(App.lastMessageId, msgData.message.message_id);
        App.loadConversations();
      }
    } catch (e) { console.error('Upload failed', e); }
    Upload.clearFile();
  },

  // ---- Bubble Builder ----
  makeBubble(msg) {
    const div = document.createElement('div');
    div.className = `message ${msg.is_mine ? 'sent' : 'received'}`;
    div.dataset.id = msg.message_id;

    let bodyHtml = '';

    // Sender name (group chats only — not needed in private 1-on-1 chat)
    const isGroup = (App.currentConvType === 'group');
    if (!msg.is_mine && msg.sender_name && isGroup) {
      bodyHtml += `<div class="msg-sender-name">${App.esc(msg.sender_name)}</div>`;
    }

    // Reply quote
    if (msg.reply_to && msg.reply_content) {
      bodyHtml += `<div class="msg-reply-quote">
        <strong>${App.esc(msg.reply_sender || 'Someone')}</strong>
        ${App.esc(msg.reply_content).substring(0, 80)}${msg.reply_content?.length > 80 ? '…' : ''}
      </div>`;
    }

    // Helper: make every URL absolute using the BROWSER's actual host.
    // This fixes images/files when accessed from a phone via local IP (10.x.x.x).
    //  - Already absolute  → return as-is
    //  - Root-relative (/InterLink/uploads/…) → prefix with window.location.origin
    //  - Relative (no slash) → prefix with BASE_URL
    const absUrl = (url) => {
      if (!url) return '';
      if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('//')) return url;
      if (url.startsWith('/')) return window.location.origin + url;  // ← key fix for mobile
      return this.BASE_URL + '/' + url;
    };

    // Content
    let contentHtml = '';
    if (msg.is_deleted) {
      contentHtml = `<em style="opacity:.5">This message was deleted</em>`;
    } else if (msg.message_type === 'image') {
      const imgSrc = absUrl(msg.content);
      contentHtml = `<img src="${imgSrc}" class="msg-image"
        onclick="Chat.openLightbox('${imgSrc}')"
        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
        alt="Image" loading="lazy">
        <a href="${imgSrc}" target="_blank" class="msg-file-link" style="display:none">
          <span class="file-icon">🖼️</span>
          <div class="msg-file-info">
            <span class="msg-file-name">View Image</span>
            <span class="msg-file-ext">Tap to open</span>
          </div>
          <span class="msg-file-dl">↗</span>
        </a>`;
    } else if (msg.message_type === 'video') {
      const vidSrc = absUrl(msg.content);
      contentHtml = `
        <video controls class="msg-video" preload="metadata" playsinline>
          <source src="${vidSrc}">
          <a href="${vidSrc}" target="_blank" class="msg-file-link">
            <span class="file-icon">🎬</span> ${App.esc(msg.content.split('/').pop())}
          </a>
        </video>`;
    } else if (msg.message_type === 'audio') {
      const audSrc = absUrl(msg.content);
      contentHtml = `
        <div class="msg-audio-wrap">
          <span class="msg-audio-icon">🎵</span>
          <audio controls class="msg-audio" preload="metadata">
            <source src="${audSrc}">
          </audio>
        </div>`;
    } else if (msg.message_type === 'file') {
      const fileSrc = absUrl(msg.content);
      const fname = msg.file_name || msg.content.split('/').pop();
      const ext = fname.split('.').pop().toUpperCase();
      contentHtml = `<a href="${fileSrc}" target="_blank" class="msg-file-link">
        <span class="file-icon">📎</span>
        <div class="msg-file-info">
          <span class="msg-file-name">${App.esc(fname)}</span>
          <span class="msg-file-ext">${ext} file</span>
        </div>
        <span class="msg-file-dl">⬇</span>
      </a>`;
    } else {
      // Plain text — preserve newlines
      contentHtml = App.esc(msg.content).replace(/\n/g, '<br>');
    }


    const editedLabel = msg.is_edited ? `<span class="msg-edited">edited</span>` : '';

    // Tick logic:
    //  • temp_* (optimistic, not yet confirmed)  → single grey ✓  (.sending)
    //  • confirmed, read_at = null               → double grey ✓✓
    //  • confirmed, read_at set                  → double red  ✓✓ (.read)
    let tickHtml = '';
    if (msg.is_mine) {
      const isTemp = String(msg.message_id).startsWith('temp');
      if (isTemp) {
        tickHtml = `<span class="msg-tick sending" title="Sending…">✓</span>`;
      } else if (msg.read_at) {
        tickHtml = `<span class="msg-tick read" title="Seen">✓✓</span>`;
      } else {
        tickHtml = `<span class="msg-tick" title="Delivered">✓✓</span>`;
      }
    }

    bodyHtml += `
      <div class="msg-bubble" oncontextmenu="Chat.showContextMenu(event, ${msg.message_id}, ${msg.is_mine})">
        ${contentHtml}
      </div>
      <div class="msg-footer">
        ${editedLabel}
        <span class="msg-time">${msg.time || this.nowTime()}</span>
        ${tickHtml}
      </div>`;

    div.innerHTML = `<div class="msg-body">${bodyHtml}</div>`;
    return div;
  },

  makeDateDivider(datetime) {
    const d = new Date(datetime);
    const now = new Date();
    let label;
    if (d.toDateString() === now.toDateString()) label = 'Today';
    else {
      const yest = new Date(now); yest.setDate(now.getDate() - 1);
      label = d.toDateString() === yest.toDateString() ? 'Yesterday' : d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }
    const div = document.createElement('div');
    div.className = 'date-divider';
    div.innerHTML = `<span>${label}</span>`;
    return div;
  },

  // ---- Context Menu ----
  showContextMenu(e, msgId, isMine) {
    e.preventDefault();
    document.querySelectorAll('.msg-context-menu').forEach(m => m.remove());
    if (!msgId || msgId.toString().startsWith('temp')) return;

    const menu = document.createElement('div');
    menu.className = 'msg-context-menu';
    menu.style.cssText = `top:${e.clientY}px;left:${e.clientX}px;position:fixed`;
    menu.innerHTML = `
      <button onclick="Chat.replyToMessage(${msgId})">↩️ Reply</button>
      ${isMine ? `
      <button onclick="Chat.editMessage(${msgId})">✏️ Edit</button>
      <button class="danger" onclick="Chat.deleteMessage(${msgId},'all')">🗑️ Delete for Everyone</button>
      <button onclick="Chat.deleteMessage(${msgId},'me')">🙈 Delete for Me</button>` : ''}
      <button onclick="Chat.copyMessage(${msgId})">📋 Copy</button>
      ${!isMine ? `<button class="danger" onclick="Chat.reportMessage(${msgId})">⚠️ Report</button>` : ''}`;
    document.body.appendChild(menu);
    e.stopPropagation();
  },

  replyToMessage(msgId) {
    const bubble = document.querySelector(`.message[data-id="${msgId}"] .msg-bubble`);
    const text = bubble?.innerText || '';
    const msgEl = document.querySelector(`.message[data-id="${msgId}"]`);
    const sender = msgEl?.querySelector('.msg-sender-name')?.innerText || 'You';
    App.replyTo = { id: msgId, content: text, sender };
    const bar = document.getElementById('reply-preview');
    if (bar) {
      bar.classList.remove('hidden');
      bar.querySelector('.reply-info').innerHTML = `<strong>${App.esc(sender)}</strong><br>${App.esc(text.substring(0, 60))}`;
    }
    document.getElementById('message-input')?.focus();
    document.querySelectorAll('.msg-context-menu').forEach(m => m.remove());
  },

  clearReply() {
    App.replyTo = null;
    document.getElementById('reply-preview')?.classList.add('hidden');
  },

  async editMessage(msgId) {
    document.querySelectorAll('.msg-context-menu').forEach(m => m.remove());
    const bubble = document.querySelector(`.message[data-id="${msgId}"] .msg-bubble`);
    const oldText = bubble?.innerText || '';
    const newText = prompt('Edit message:', oldText);
    if (!newText || newText === oldText) return;
    const res = await postForm(`${this.BASE_URL}/api/messages/edit.php`, { message_id: msgId, content: newText });
    const data = await res.json();
    if (data.success) {
      if (bubble) bubble.innerHTML = App.esc(newText) + '<span class="msg-edited" style="margin-left:6px">edited</span>';
    } else { alert(data.error || 'Edit failed'); }
  },

  async deleteMessage(msgId, scope) {
    document.querySelectorAll('.msg-context-menu').forEach(m => m.remove());
    if (!confirm(`Delete this message ${scope === 'all' ? 'for everyone' : 'for you'}?`)) return;
    const res = await postForm(`${this.BASE_URL}/api/messages/delete.php`, { message_id: msgId, scope });
    const data = await res.json();
    if (data.success && scope === 'all') {
      const bubble = document.querySelector(`.message[data-id="${msgId}"] .msg-bubble`);
      if (bubble) bubble.innerHTML = `<em style="opacity:.5">This message was deleted</em>`;
    }
  },

  copyMessage(msgId) {
    document.querySelectorAll('.msg-context-menu').forEach(m => m.remove());
    const bubble = document.querySelector(`.message[data-id="${msgId}"] .msg-bubble`);
    if (bubble) navigator.clipboard.writeText(bubble.innerText);
  },

  async reportMessage(msgId) {
    document.querySelectorAll('.msg-context-menu').forEach(m => m.remove());
    const reason = prompt('Why are you reporting this message?');
    if (!reason || !reason.trim()) return;
    try {
      const res = await postForm(`${this.BASE_URL}/api/reports/submit.php`, { message_id: msgId, reason: reason.trim() });
      const data = await res.json();
      if (data.success) {
        alert('✅ ' + (data.message || 'Report submitted. Thank you.'));
      } else {
        alert('⚠️ ' + (data.error || 'Failed to submit report.'));
      }
    } catch (e) {
      alert('⚠️ Connection error. Please try again.');
    }
  },

  // ---- Lightbox ---- (mobile-friendly with close button)
  openLightbox(src) {
    const overlay = document.createElement('div');
    overlay.style.cssText = [
      'position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:9999;',
      'display:flex;flex-direction:column;align-items:center;justify-content:center;',
      'padding:env(safe-area-inset-top,16px) 16px env(safe-area-inset-bottom,16px);',
      'cursor:zoom-out;'
    ].join('');
    overlay.innerHTML = `
      <button style="position:absolute;top:max(16px,env(safe-area-inset-top,16px));right:16px;
        background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);
        border-radius:50%;width:36px;height:36px;font-size:1rem;color:#fff;
        display:flex;align-items:center;justify-content:center;"
        onclick="this.parentNode.remove()">✕</button>
      <img src="${src}" style="max-width:100%;max-height:85vh;border-radius:10px;
        box-shadow:0 0 60px rgba(0,0,0,.8);object-fit:contain;">
      <a href="${src}" download target="_blank"
        style="margin-top:16px;color:rgba(255,255,255,0.6);font-size:.85rem;text-decoration:none;
          background:rgba(255,255,255,0.08);padding:8px 20px;border-radius:99px;
          border:1px solid rgba(255,255,255,0.1)">
        ⬇ Save Image
      </a>`;
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
    document.body.appendChild(overlay);
  },

  // ---- Emoji Picker ----
  toggleEmoji() {
    let picker = document.getElementById('emoji-picker');
    if (picker) { picker.remove(); return; }
    picker = document.createElement('div');
    picker.id = 'emoji-picker';
    picker.className = 'emoji-picker-wrap';
    picker.innerHTML = `<div class="emoji-grid">${this.EMOJIS.map(e => `<button class="emoji-btn" onclick="Chat.insertEmoji('${e}')">${e}</button>`).join('')}</div>`;
    document.getElementById('input-bar-wrap')?.appendChild(picker);
  },

  insertEmoji(emoji) {
    const input = document.getElementById('message-input');
    if (!input) return;
    const pos = input.selectionStart;
    input.value = input.value.slice(0, pos) + emoji + input.value.slice(pos);
    input.focus();
    input.setSelectionRange(pos + emoji.length, pos + emoji.length);
  },

  scrollToBottom(el, smooth = false) {
    if (!el) return;
    const doScroll = () => {
      el.scrollTop = el.scrollHeight;
    };
    // Use rAF so scroll fires after paint (critical on mobile)
    requestAnimationFrame(() => {
      doScroll();
      // Double-tap for stubborn iOS cases
      requestAnimationFrame(doScroll);
    });
  },

  nowTime() {
    return new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
  }
};

// ---- Input Handling ----
window.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('message-input');
  if (!input) return;

  // Auto-resize textarea
  input.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';

    // Instagram DM toggle: show send btn when typing, show action icons when empty
    const hasText = input.value.trim().length > 0 || !!App.pendingFile;
    const sendBtn = document.getElementById('send-btn');
    const ibActions = document.getElementById('ib-actions');
    if (sendBtn) sendBtn.style.display = hasText ? 'flex' : 'none';
    if (ibActions) ibActions.style.display = hasText ? 'none' : 'flex';
  });

  // Send on Enter (Shift+Enter = newline)
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      Chat.sendMessage();
    }
  });

  // Request notification permission
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
  }

  // ---- Mobile keyboard / viewport fix (Instagram / Telegram style) ----
  // Problem: When the Android keyboard opens, Chrome shrinks the viewport and
  // `position:fixed` elements shift — the chat HEADER gets pushed off screen.
  // Fix: Lock .chat-layout height to visualViewport.height on every resize so
  // the header always stays visible and ONLY the messages area shrinks.
  if (window.visualViewport) {
    const layout = document.querySelector('.chat-layout');

    const applyVpHeight = () => {
      const vh = window.visualViewport.height;
      if (layout) {
        layout.style.height = vh + 'px';
        layout.style.maxHeight = vh + 'px';
      }
      // Always scroll to bottom when keyboard opens (latest message stays visible)
      const area = document.getElementById('messages-area');
      if (area) {
        requestAnimationFrame(() => {
          area.scrollTop = area.scrollHeight;
        });
      }
    };

    // Apply on resize (keyboard open/close) and on scroll (iOS offset correction)
    window.visualViewport.addEventListener('resize', applyVpHeight);
    window.visualViewport.addEventListener('scroll', applyVpHeight);

    // Set initial height immediately
    applyVpHeight();
  }
});
