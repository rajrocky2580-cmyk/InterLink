// =============================================================
// InterLink — File Upload Handler (upload.js)
// =============================================================

const Upload = {
  triggerFilePicker() {
    document.getElementById('file-input')?.click();
  },

  handleFileChange(input) {
    const file = input.files?.[0];
    if (!file) return;

    if (file.size > 10 * 1024 * 1024) {
      alert('File too large. Maximum size is 10MB.');
      input.value = '';
      return;
    }

    App.pendingFile = file;
    const bar  = document.getElementById('file-preview-bar');
    const name = document.getElementById('file-preview-name');
    const img  = document.getElementById('file-preview-img');
    if (!bar) return;

    bar.classList.remove('hidden');
    if (name) name.textContent = file.name;

    if (file.type.startsWith('image/') && img) {
      // Image thumbnail
      const reader = new FileReader();
      reader.onload = e => { img.src = e.target.result; img.classList.remove('hidden'); };
      reader.readAsDataURL(file);
    } else if (file.type.startsWith('video/') && img) {
      // Video thumbnail via object URL
      img.src = '';
      img.classList.add('hidden');
      // Show a video icon in the name area
      if (name) name.textContent = '🎬 ' + file.name;
    } else if (file.type.startsWith('audio/') && img) {
      img.classList.add('hidden');
      if (name) name.textContent = '🎵 ' + file.name;
    } else if (img) {
      img.classList.add('hidden');
    }
  },

  clearFile() {
    App.pendingFile = null;
    const bar   = document.getElementById('file-preview-bar');
    const input = document.getElementById('file-input');
    const img   = document.getElementById('file-preview-img');
    if (bar)   bar.classList.add('hidden');
    if (input) input.value = '';
    if (img)   { img.src = ''; img.classList.add('hidden'); }
  }
};
