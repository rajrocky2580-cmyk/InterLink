// =============================================================
// InterLink — WebRTC Voice & Video Call Module (call.js)
// Fixed: HTTP/no-mediaDevices graceful fallback, SDP extraction,
//        ICE candidate buffering, adaptive polling, ringtone,
//        mobile toast positioning, call_signals table dependency.
// =============================================================

const Call = {
  BASE_URL: document.querySelector('meta[name="base-url"]')?.content || '',

  // ── State ──────────────────────────────────────────────────
  pc:                  null,
  localStream:         null,
  callActive:          false,
  isVideo:             false,
  isCaller:            false,
  toUserId:            null,
  toUserName:          '',
  convId:              null,
  _pendingOffer:       null,
  _pendingCandidates:  [],   // buffered ICE until remote desc is ready
  _callTimer:          null,
  _callSeconds:        0,
  _ringTimer:          null,
  _pollingActive:      false,
  _ringInterval:       null, // Web Audio ringtone interval

  ICE_SERVERS: {
    iceServers: [
      { urls: 'stun:stun.l.google.com:19302'  },
      { urls: 'stun:stun1.l.google.com:19302' },
      { urls: 'stun:stun2.l.google.com:19302' },
      { urls: 'stun:stun.cloudflare.com:3478' },
    ]
  },

  // ── Polling (recursive setTimeout for adaptive speed) ──────
  startPolling() {
    if (this._pollingActive) return;
    this._pollingActive = true;
    this._schedulePoll();
  },

  _schedulePoll() {
    const delay = (this.callActive || this.isCaller || this._pendingOffer) ? 800 : 1500;
    setTimeout(() => this._runPoll(), delay);
  },

  async _runPoll() {
    if (!this._pollingActive) return;
    try {
      const res  = await fetch(`${this.BASE_URL}/api/calls/poll.php`, { cache: 'no-store' });
      if (res.ok) {
        const data = await res.json();
        if (data.db_error) { console.warn('[Call] Server:', data.db_error); }
        if (Array.isArray(data.signals)) {
          for (const sig of data.signals) {
            await this.handleSignal(sig);
          }
        }
      }
    } catch (_) { /* network error — keep polling */ }
    this._schedulePoll();
  },

  // ── Attempt to get media with graceful HTTP fallback ───────
  async _getMedia(wantVideo) {
    // 1. Modern API (requires HTTPS on mobile Chrome)
    if (typeof navigator.mediaDevices?.getUserMedia === 'function') {
      return await navigator.mediaDevices.getUserMedia({
        audio: true,
        video: wantVideo
          ? { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' }
          : false
      });
    }

    // 2. Legacy webkit/moz (older Android)
    const legacyGUM = navigator.webkitGetUserMedia || navigator.mozGetUserMedia;
    if (typeof legacyGUM === 'function') {
      return await new Promise((resolve, reject) => {
        legacyGUM.call(navigator, { audio: true, video: wantVideo }, resolve, reject);
      });
    }

    // 3. Nothing worked — likely HTTP on mobile
    const err = new DOMException(
      'Microphone/camera not available. Calls require HTTPS on mobile browsers.\n\nTo enable: Open chrome://flags → "Insecure origins treated as secure" → add ' + location.origin,
      'NotSupportedError'
    );
    err._isHttpIssue = true;
    throw err;
  },

  // ── Initiate outgoing call ──────────────────────────────────
  async startCall(isVideo) {
    if (this.callActive) { this._toast('⚠️ Already in a call'); return; }

    const toUserId = App.currentConvUserId;
    const name     = document.querySelector('.chat-header-name')?.textContent?.trim() || 'User';
    if (!toUserId) { this._toast('⚠️ Open a private conversation first'); return; }

    this.isVideo    = isVideo;
    this.isCaller   = true;
    this.toUserId   = toUserId;
    this.toUserName = name;
    this.convId     = App.currentConvId;
    this._pendingCandidates = [];

    this._showModal('calling', name, isVideo);

    try {
      this.localStream = await this._getMedia(isVideo);
      this._attachLocalStream(this.localStream);
      this._buildPC();
      this.localStream.getTracks().forEach(t => this.pc.addTrack(t, this.localStream));

      const offer = await this.pc.createOffer({
        offerToReceiveAudio: true,
        offerToReceiveVideo: isVideo
      });
      await this.pc.setLocalDescription(offer);

      const ok = await this._signal('offer', { sdp: offer, call_type: isVideo ? 'video' : 'voice' });
      if (!ok) {
        this._toast('⚠️ Could not reach server. Check connection or run setup_db.php.');
        this._cleanup();
        this._hideModal();
        return;
      }

      // No answer within 45 s → cancel
      this._ringTimer = setTimeout(() => {
        this._showEnded('No answer');
        this.endCall(false);
      }, 45000);

    } catch (e) {
      console.error('[Call] startCall error:', e);
      this._hideModal();
      if (e._isHttpIssue || e.name === 'NotSupportedError') {
        this._httpsWarning();
      } else if (e.name === 'NotAllowedError') {
        this._toast('⚠️ Allow camera/mic in your browser settings.');
      } else if (e.name === 'NotFoundError') {
        this._toast('⚠️ No camera/microphone found.');
      } else {
        this._toast('⚠️ Cannot start call: ' + e.message);
      }
      this._cleanup();
    }
  },

  // ── Handle an incoming signal ───────────────────────────────
  async handleSignal(sig) {
    console.log('[Call] Signal:', sig.type, sig);

    switch (sig.type) {

      case 'offer': {
        if (this.callActive) {
          await this._signal('busy', {}, sig.from_user, sig.conversation_id);
          return;
        }
        if (this._pendingOffer) {
          await this._signal('busy', {}, sig.from_user, sig.conversation_id);
          return;
        }
        const isVid            = sig.payload?.call_type === 'video';
        this.isVideo           = isVid;
        this.isCaller          = false;
        this.toUserId          = sig.from_user;
        this.toUserName        = sig.from_name || 'Unknown';
        this.convId            = sig.conversation_id;
        // Normalize SDP: server may send payload.sdp (object) or payload (raw SDP object)
        this._pendingOffer     = sig.payload?.sdp ?? sig.payload;
        this._pendingCandidates = [];

        this._showModal('incoming', this.toUserName, isVid);
        this._startRing();

        // Auto-reject after 45 s
        this._ringTimer = setTimeout(() => this.rejectCall(), 45000);
        break;
      }

      case 'answer': {
        if (!this.pc) return;
        clearTimeout(this._ringTimer);
        // Normalize SDP from answer signal
        const sdp = sig.payload?.sdp ?? sig.payload;
        try {
          await this.pc.setRemoteDescription(new RTCSessionDescription(sdp));
          await this._flushCandidates();
        } catch (e) { console.error('[Call] setRemoteDescription(answer):', e); }
        break;
      }

      case 'ice-candidate': {
        if (!sig.payload) return;
        const candidate = new RTCIceCandidate(sig.payload);
        if (this.pc && this.pc.remoteDescription) {
          try { await this.pc.addIceCandidate(candidate); }
          catch (e) { console.warn('[Call] addIceCandidate:', e); }
        } else {
          this._pendingCandidates.push(candidate);
        }
        break;
      }

      case 'hangup': this._showEnded((sig.from_name||'User') + ' ended the call'); this.endCall(false); break;
      case 'reject': this._showEnded('Call declined');  this.endCall(false); break;
      case 'busy':   this._showEnded('User is busy');   this.endCall(false); break;
    }
  },

  async _flushCandidates() {
    if (!this.pc || !this.pc.remoteDescription) return;
    for (const c of this._pendingCandidates) {
      try { await this.pc.addIceCandidate(c); }
      catch (e) { console.warn('[Call] flush candidate:', e); }
    }
    this._pendingCandidates = [];
  },

  // ── Accept incoming call ────────────────────────────────────
  async acceptCall() {
    clearTimeout(this._ringTimer);
    this._stopRing();

    if (!this._pendingOffer) {
      this._toast('⚠️ Call expired — ask them to call again.');
      this._hideModal();
      return;
    }

    try {
      // Try to get media — _getMedia handles HTTP fallback
      this.localStream = await this._getMedia(this.isVideo);

    } catch (e) {
      console.error('[Call] acceptCall media error:', e);

      if (e._isHttpIssue || e.name === 'NotSupportedError') {
        // Show non-blocking HTTP warning but still try to continue audio-only
        // via the legacy polyfill approach — if that also fails, show warning
        this._hideModal();
        this._cleanup();
        this._httpsWarning();
        return;
      }

      if (e.name === 'NotAllowedError') {
        this._toast('⚠️ Please allow microphone access in your browser settings.');
        await this.rejectCall();
        return;
      }

      if (e.name === 'NotFoundError') {
        this._toast('⚠️ No microphone found on this device.');
        await this.rejectCall();
        return;
      }

      // Unknown error
      this._toast('⚠️ Cannot join call: ' + e.message);
      await this.rejectCall();
      return;
    }

    try {
      this._attachLocalStream(this.localStream);
      this._buildPC();
      this.localStream.getTracks().forEach(t => this.pc.addTrack(t, this.localStream));

      // setRemoteDescription with the buffered offer SDP
      const offerDesc = new RTCSessionDescription(
        this._pendingOffer?.type ? this._pendingOffer : { type: 'offer', sdp: this._pendingOffer }
      );
      await this.pc.setRemoteDescription(offerDesc);
      this._pendingOffer = null;

      // Flush ICE candidates that arrived before we set remote desc
      await this._flushCandidates();

      const answer = await this.pc.createAnswer();
      await this.pc.setLocalDescription(answer);
      await this._signal('answer', { sdp: answer });

      this._onConnected();

    } catch (e) {
      console.error('[Call] acceptCall webrtc error:', e);
      this._toast('⚠️ Call connection failed: ' + e.message);
      await this.rejectCall();
    }
  },

  // ── Reject / Hang up ───────────────────────────────────────
  async rejectCall() {
    clearTimeout(this._ringTimer);
    this._stopRing();
    this._pendingOffer = null;
    await this._signal('reject', {});
    this._cleanup();
    this._hideModal();
  },

  async endCall(sendHangup = true) {
    clearTimeout(this._ringTimer);
    this._stopRing();
    if (sendHangup && this.toUserId) {
      await this._signal('hangup', {}).catch(() => {});
    }
    this._cleanup();
    this._hideModal();
  },

  // ── Controls during active call ────────────────────────────
  toggleMute() {
    const track = this.localStream?.getAudioTracks()[0];
    if (!track) return;
    track.enabled = !track.enabled;
    const btn = document.getElementById('call-mute-btn');
    if (btn) {
      btn.innerHTML = track.enabled ? '🎤' : '🔇';
      btn.title     = track.enabled ? 'Mute' : 'Unmute';
      btn.classList.toggle('call-ctrl-active', !track.enabled);
    }
  },

  toggleCamera() {
    const track = this.localStream?.getVideoTracks()[0];
    if (!track) return;
    track.enabled = !track.enabled;
    const btn = document.getElementById('call-cam-btn');
    if (btn) {
      btn.classList.toggle('call-ctrl-active', !track.enabled);
      btn.title = track.enabled ? 'Turn off camera' : 'Turn on camera';
    }
  },

  toggleSpeaker() {
    const vid = document.getElementById('call-remote-video');
    if (!vid) return;
    vid.muted = !vid.muted;
    const btn = document.getElementById('call-spk-btn');
    if (btn) btn.classList.toggle('call-ctrl-active', vid.muted);
  },

  // ── Build RTCPeerConnection ─────────────────────────────────
  _buildPC() {
    this.pc = new RTCPeerConnection(this.ICE_SERVERS);

    this.pc.onicecandidate = e => {
      if (e.candidate) this._signal('ice-candidate', e.candidate.toJSON());
    };

    this.pc.ontrack = e => {
      const vid = document.getElementById('call-remote-video');
      if (vid && e.streams[0]) vid.srcObject = e.streams[0];
    };

    this.pc.oniceconnectionstatechange = () => {
      const s = this.pc?.iceConnectionState;
      console.log('[Call] ICE state:', s);
      if (s === 'failed') { this._showEnded('Connection failed'); this.endCall(false); }
    };

    this.pc.onconnectionstatechange = () => {
      const s = this.pc?.connectionState;
      console.log('[Call] Connection state:', s);
      if (s === 'connected'    && !this.callActive) this._onConnected();
      if (s === 'failed')      { this._showEnded('Connection lost'); this.endCall(false); }
      if (s === 'disconnected') console.warn('[Call] Peer disconnected, may reconnect…');
    };
  },

  _onConnected() {
    if (this.callActive) return;
    this.callActive = true;
    clearTimeout(this._ringTimer);

    const modal = document.getElementById('call-modal');
    if (!modal) return;
    modal.dataset.mode = 'active';

    modal.querySelector('.call-incoming-btns')?.classList.add('hidden');
    modal.querySelector('.call-calling-hint')?.classList.add('hidden');
    modal.querySelector('.call-hangup-wrap')?.classList.add('hidden');
    modal.querySelector('.call-active-btns')?.classList.remove('hidden');

    const videoArea = modal.querySelector('.call-video-area');
    if (this.isVideo) {
      videoArea?.classList.remove('hidden');
      const camBtn = document.getElementById('call-cam-btn');
      if (camBtn) camBtn.style.display = 'flex';
    } else {
      videoArea?.classList.add('hidden');
    }

    const status = modal.querySelector('.call-status');
    if (status) { status.textContent = '00:00'; status.style.color = ''; }

    this._callSeconds = 0;
    this._callTimer = setInterval(() => {
      this._callSeconds++;
      const m  = String(Math.floor(this._callSeconds / 60)).padStart(2, '0');
      const s  = String(this._callSeconds % 60).padStart(2, '0');
      const el = document.querySelector('#call-modal .call-status');
      if (el) el.textContent = `${m}:${s}`;
    }, 1000);
  },

  _cleanup() {
    clearInterval(this._callTimer);
    this._callTimer          = null;
    this._callSeconds        = 0;
    this.localStream?.getTracks().forEach(t => t.stop());
    this.localStream         = null;
    this.pc?.close();
    this.pc                  = null;
    this.callActive          = false;
    this.isCaller            = false;
    this.toUserId            = null;
    this._pendingOffer       = null;
    this._pendingCandidates  = [];
  },

  // ── Modal UI ───────────────────────────────────────────────
  _showModal(mode, name, isVideo) {
    const modal = document.getElementById('call-modal');
    if (!modal) return;
    modal.classList.add('active');
    modal.dataset.mode = mode;

    const nameEl    = modal.querySelector('.call-user-name');
    const statusEl  = modal.querySelector('.call-status');
    const iconEl    = modal.querySelector('.call-type-icon');
    const incBtns   = modal.querySelector('.call-incoming-btns');
    const actBtns   = modal.querySelector('.call-active-btns');
    const hint      = modal.querySelector('.call-calling-hint');
    const videoArea = modal.querySelector('.call-video-area');
    const hangWrap  = modal.querySelector('.call-hangup-wrap');

    if (nameEl) nameEl.textContent = name;
    if (iconEl) iconEl.textContent = isVideo ? '📹' : '📞';

    incBtns?.classList.add('hidden');
    actBtns?.classList.add('hidden');
    videoArea?.classList.add('hidden');
    hint?.classList.add('hidden');
    hangWrap?.classList.remove('hidden');

    if (mode === 'incoming') {
      if (statusEl) statusEl.textContent = isVideo ? '📹 Incoming video call…' : '📞 Incoming voice call…';
      incBtns?.classList.remove('hidden');
      hangWrap?.classList.add('hidden');
    } else if (mode === 'calling') {
      if (statusEl) statusEl.textContent = 'Calling…';
      hint?.classList.remove('hidden');
    }
  },

  _hideModal() {
    const modal = document.getElementById('call-modal');
    if (modal) { modal.classList.remove('active'); modal.dataset.mode = ''; }
    ['call-local-video', 'call-remote-video'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.srcObject = null;
    });
    document.querySelector('#call-modal .call-hangup-wrap')?.classList.remove('hidden');
  },

  _showEnded(msg) {
    clearInterval(this._callTimer);
    const status = document.querySelector('#call-modal .call-status');
    if (status) { status.textContent = msg; status.style.color = '#ef4444'; }
    setTimeout(() => this._hideModal(), 2500);
  },

  _attachLocalStream(stream) {
    const vid = document.getElementById('call-local-video');
    if (vid) { vid.srcObject = stream; vid.muted = true; }
  },

  // ── Ringtone (Web Audio API — no external file needed) ──────
  _startRing() {
    document.querySelector('#call-modal .call-avatar-ring')?.classList.add('ringing');
    this._playRing();
    this._ringInterval = setInterval(() => this._playRing(), 3000);
  },

  _stopRing() {
    document.querySelector('#call-modal .call-avatar-ring')?.classList.remove('ringing');
    clearInterval(this._ringInterval);
    this._ringInterval = null;
  },

  _playRing() {
    try {
      const ctx  = new (window.AudioContext || window.webkitAudioContext)();
      const freqs = [880, 1100, 880, 1100];
      let time = ctx.currentTime;
      freqs.forEach(freq => {
        const osc  = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type      = 'sine';
        osc.frequency.setValueAtTime(freq, time);
        gain.gain.setValueAtTime(0.3, time);
        gain.gain.exponentialRampToValueAtTime(0.001, time + 0.2);
        osc.start(time);
        osc.stop(time + 0.2);
        time += 0.22;
      });
    } catch (_) { /* AudioContext not supported */ }
  },

  // ── HTTPS Warning (shown when getUserMedia unavailable on HTTP) ──
  _httpsWarning() {
    const origin = location.origin;
    const el = document.createElement('div');
    el.style.cssText = [
      'position:fixed', 'inset:0', 'z-index:99999',
      'background:rgba(10,14,26,0.96)', 'display:flex',
      'align-items:center', 'justify-content:center',
      'padding:24px', 'backdrop-filter:blur(8px)'
    ].join(';');
    el.innerHTML = `
      <div style="background:#131b2e;border:1px solid rgba(239,68,68,0.4);border-radius:20px;padding:32px 28px;max-width:420px;width:100%;text-align:center">
        <div style="font-size:2.5rem;margin-bottom:12px">📵</div>
        <h3 style="font-size:1.1rem;font-weight:700;color:#f1f5f9;margin-bottom:10px">HTTPS Required for Calls</h3>
        <p style="font-size:.875rem;color:#94a3b8;line-height:1.6;margin-bottom:20px">
          Microphone &amp; camera access requires a secure (HTTPS) connection on mobile browsers.
        </p>
        <div style="background:rgba(79,142,247,0.08);border:1px solid rgba(79,142,247,0.2);border-radius:12px;padding:16px;text-align:left;font-size:.8125rem;color:#cbd5e1;line-height:1.9;margin-bottom:20px">
          <strong style="color:#60a5fa">Fix in Chrome (Android):</strong><br>
          1. Type <code style="background:#0f1629;padding:2px 6px;border-radius:4px">chrome://flags</code> in address bar<br>
          2. Search <strong>"Insecure origins treated as secure"</strong><br>
          3. Add: <code style="background:#0f1629;padding:2px 6px;border-radius:4px;word-break:break-all">${origin}</code><br>
          4. Tap <strong>Relaunch</strong> — calls will work!
        </div>
        <button onclick="this.closest('div[style]').remove()" style="background:linear-gradient(135deg,#4f8ef7,#3a74d9);color:#fff;border:none;border-radius:12px;padding:12px 28px;font-size:.9375rem;font-weight:600;cursor:pointer;width:100%">Got it</button>
      </div>`;
    document.body.appendChild(el);
  },

  // ── Toast notification ─────────────────────────────────────
  _toast(msg) {
    const t = document.createElement('div');
    // On mobile, keep toast above the bottom safe area
    const isMobile = window.innerWidth <= 640;
    t.style.cssText = [
      'position:fixed',
      isMobile ? 'bottom:80px' : 'bottom:24px',
      'left:50%', 'transform:translateX(-50%)',
      'background:rgba(30,35,55,0.97)', 'color:#f1f5f9',
      'border:1px solid rgba(239,68,68,0.4)',
      'padding:12px 22px', 'border-radius:12px',
      'font-size:.875rem', 'z-index:99999',
      'font-weight:600', 'backdrop-filter:blur(12px)',
      'box-shadow:0 8px 32px rgba(0,0,0,0.5)',
      'max-width:calc(100vw - 32px)', 'text-align:center',
      'white-space:pre-wrap'
    ].join(';');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 4500);
  },

  // ── Signaling — returns true on success, false on server error
  async _signal(type, payload, overrideToUser = null, overrideConv = null) {
    const toUser = overrideToUser || this.toUserId;
    if (!toUser) return false;
    try {
      const res = await fetch(`${this.BASE_URL}/api/calls/signal.php`, {
        method: 'POST',
        body: new URLSearchParams({
          to_user:         toUser,
          conversation_id: overrideConv || this.convId || 0,
          type,
          payload:         JSON.stringify(payload)
        })
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        console.error('[Call] signal.php error:', data.error || res.status);
        return false;
      }
      return true;
    } catch (e) {
      console.error('[Call] _signal network error:', e);
      return false;
    }
  }
};

// Boot polling as soon as the page loads
window.addEventListener('DOMContentLoaded', () => Call.startPolling());
