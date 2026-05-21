// =============================================================
// InterLink — Notifications Module (notifications.js)
// =============================================================

const Notifications = {
  permission: Notification.permission,

  async requestPermission() {
    if (!('Notification' in window)) return;
    if (this.permission === 'default') {
      this.permission = await Notification.requestPermission();
    }
  },

  show(title, body, icon) {
    if (document.hidden && this.permission === 'granted') {
      const n = new Notification(title, { body, icon: icon || '/InterLink/assets/images/icon.png' });
      setTimeout(() => n.close(), 5000);
      n.onclick = () => { window.focus(); n.close(); };
    }
  }
};

// Auto-request on load
window.addEventListener('DOMContentLoaded', () => Notifications.requestPermission());
