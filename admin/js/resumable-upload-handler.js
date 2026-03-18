/**
 * ResumableUploadHandler
 *
 * Features
 * ────────
 * • Chunked uploads  – files are split into 2 MB slices and each chunk is
 *   uploaded individually, so a single large video never requires one giant POST.
 * • IndexedDB persistence – upload progress is saved to the browser's IndexedDB
 *   after every successful chunk, so the upload can survive page refreshes,
 *   network drops, and computer restarts.
 * • Automatic resume  – on page load the handler checks IndexedDB for incomplete
 *   sessions belonging to the current folder and offers to resume them.
 * • Duplicate detection – before starting, the handler queries the server for
 *   file names that already exist in the target folder.  For each duplicate the
 *   user is asked whether to Replace or Skip it.
 * • Per-file progress – each file card in the preview shows a progress bar
 *   and a status badge updated in real-time.
 */

class ResumableUploadHandler {
    // ── Configuration ────────────────────────────────────────────────────────

    static CHUNK_SIZE   = 2 * 1024 * 1024; // 2 MB per chunk
    static MAX_RETRIES  = 3;                // retries per chunk on network error
    static RETRY_DELAY  = 2000;             // ms between retries
    static VIDEO_TYPES  = [
        'video/mp4', 'video/quicktime', 'video/x-msvideo',
        'video/webm', 'video/x-matroska', 'video/mpeg', 'video/3gpp',
    ];

    // ── Constructor ──────────────────────────────────────────────────────────

    constructor(options = {}) {
        const defaults = {
            fileInput:        '#images',
            dropZone:         '#dropZone',
            previewContainer: '#imagePreviewContainer',
            uploadButton:     '#uploadButton',
            form:             '#uploadForm',
            folderId:         0,
            maxPhotoSize:     20 * 1024 * 1024,        // 20 MB
            maxVideoSize:     8  * 1024 * 1024 * 1024, // 8 GB
            allowedPhotoTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            checkDuplicatesUrl: 'ajax-check-duplicates.php',
            uploadChunkUrl:     'ajax-upload-chunk.php',
            completeUploadUrl:  'ajax-complete-upload.php',
            onUploadComplete:  () => {},
        };

        this.options          = { ...defaults, ...options };
        this.files            = [];    // File objects (null if removed)
        this.db               = null;  // IDBDatabase
        this.isUploading      = false;
        this._init();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    isVideoFile(file) {
        return ResumableUploadHandler.VIDEO_TYPES.includes(file.type);
    }

    /** Generate a 64-char hex session ID (browser crypto). */
    _generateSessionId() {
        const arr = new Uint8Array(32);
        crypto.getRandomValues(arr);
        return Array.from(arr).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    /** Slice a File into an array of Blob chunks. */
    _sliceFile(file) {
        const chunks = [];
        const size   = ResumableUploadHandler.CHUNK_SIZE;
        for (let start = 0; start < file.size; start += size) {
            chunks.push(file.slice(start, Math.min(start + size, file.size)));
        }
        return chunks.length > 0 ? chunks : [file.slice(0)]; // at least 1 chunk
    }

    _sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    _escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    _formatSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const s = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return (bytes / Math.pow(k, i)).toFixed(1) + ' ' + s[i];
    }

    _truncateName(name, max = 22) {
        if (name.length <= max) return name;
        const ext  = name.split('.').pop();
        const base = name.substring(0, name.length - ext.length - 1);
        return base.substring(0, max - ext.length - 4) + '….' + ext;
    }

    // ── IndexedDB ────────────────────────────────────────────────────────────

    async _openDB() {
        if (this.db) return;
        return new Promise((resolve, reject) => {
            const req = indexedDB.open('venubooking_uploads', 1);
            req.onupgradeneeded = (e) => {
                const db    = e.target.result;
                const store = db.createObjectStore('sessions', { keyPath: 'sessionId' });
                store.createIndex('folderId', 'folderId', { unique: false });
            };
            req.onsuccess = (e) => { this.db = e.target.result; resolve(); };
            req.onerror   = ()  => reject(req.error);
        });
    }

    async _saveSession(session) {
        await this._openDB();
        return new Promise((resolve, reject) => {
            const tx  = this.db.transaction('sessions', 'readwrite');
            const req = tx.objectStore('sessions').put(session);
            req.onsuccess = () => resolve();
            req.onerror   = () => reject(req.error);
        });
    }

    async _getSession(sessionId) {
        await this._openDB();
        return new Promise((resolve, reject) => {
            const tx  = this.db.transaction('sessions', 'readonly');
            const req = tx.objectStore('sessions').get(sessionId);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror   = () => reject(req.error);
        });
    }

    async _deleteSession(sessionId) {
        await this._openDB();
        return new Promise((resolve, reject) => {
            const tx  = this.db.transaction('sessions', 'readwrite');
            const req = tx.objectStore('sessions').delete(sessionId);
            req.onsuccess = () => resolve();
            req.onerror   = () => reject(req.error);
        });
    }

    /** Return all incomplete sessions for the current folder. */
    async _getPendingSessions() {
        await this._openDB();
        return new Promise((resolve, reject) => {
            const folderId = this.options.folderId;
            const tx       = this.db.transaction('sessions', 'readonly');
            const index    = tx.objectStore('sessions').index('folderId');
            const req      = index.getAll(IDBKeyRange.only(folderId));
            req.onsuccess  = () => {
                const pending = (req.result || []).filter(
                    s => s.status !== 'complete' && s.totalChunks > 0
                );
                resolve(pending);
            };
            req.onerror = () => reject(req.error);
        });
    }

    // ── DOM helpers ──────────────────────────────────────────────────────────

    _initUI() {
        this.fileInput        = document.querySelector(this.options.fileInput);
        this.dropZone         = document.querySelector(this.options.dropZone);
        this.previewContainer = document.querySelector(this.options.previewContainer);
        this.uploadButton     = document.querySelector(this.options.uploadButton);
        this.form             = document.querySelector(this.options.form);

        if (this.fileInput) {
            this.fileInput.addEventListener('change', e => this._handleFileSelect(e));
        }

        if (this.dropZone) {
            this._setupDragDrop();
        }

        if (this.form) {
            this.form.addEventListener('submit', e => this._handleSubmit(e));
        }
    }

    _setupDragDrop() {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev => {
            this.dropZone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); });
        });
        ['dragenter', 'dragover'].forEach(ev => {
            this.dropZone.addEventListener(ev, () => this.dropZone.classList.add('drag-over'));
        });
        ['dragleave', 'drop'].forEach(ev => {
            this.dropZone.addEventListener(ev, () => this.dropZone.classList.remove('drag-over'));
        });
        this.dropZone.addEventListener('drop', e => {
            this._addFiles(Array.from(e.dataTransfer.files));
        });
        this.dropZone.addEventListener('click', () => this.fileInput && this.fileInput.click());
    }

    _handleFileSelect(e) {
        this._addFiles(Array.from(e.target.files));
    }

    _addFiles(files) {
        const isPhoto = f => this.options.allowedPhotoTypes.includes(f.type);
        const isVideo = f => this.isVideoFile(f);

        for (const file of files) {
            if (!isPhoto(file) && !isVideo(file)) {
                this._alert('error', `${this._escapeHtml(file.name)}: Invalid file type.`);
                continue;
            }
            if (isVideo(file) && file.size > this.options.maxVideoSize) {
                this._alert('error', `${this._escapeHtml(file.name)}: Video too large (${this._formatSize(file.size)}).`);
                continue;
            }
            if (isPhoto(file) && file.size > this.options.maxPhotoSize) {
                this._alert('error', `${this._escapeHtml(file.name)}: Photo too large (${this._formatSize(file.size)}).`);
                continue;
            }

            // Skip exact duplicate in current selection
            const alreadyQueued = this.files.some(
                f => f && f.name === file.name && f.size === file.size
            );
            if (alreadyQueued) continue;

            const index = this.files.length;
            this.files.push(file);
            this._addPreviewCard(file, index);
        }

        this._updateUploadBtn();
    }

    _addPreviewCard(file, index) {
        const isVideo = this.isVideoFile(file);
        const card    = document.createElement('div');
        card.className         = 'image-preview-item';
        card.dataset.index     = index;
        card.innerHTML = `
            <div class="preview-image-container">
                ${isVideo
                    ? `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#666;">
                           <i class="fas fa-video" style="font-size:2rem;color:#dc3545;"></i>
                           <small style="margin-top:4px;font-size:0.65rem;color:#888;">VIDEO</small>
                       </div>`
                    : `<div class="preview-loading"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading…</span></div></div>`
                }
            </div>
            <div class="preview-info">
                <div class="preview-name" title="${this._escapeHtml(file.name)}">${this._truncateName(file.name)}</div>
                <div class="preview-size">${this._formatSize(file.size)}</div>
                <div class="preview-status"><span class="badge bg-secondary">Pending</span></div>
            </div>
            <button type="button" class="preview-remove" data-index="${index}" title="Remove">
                <i class="fas fa-times"></i>
            </button>
            <div class="preview-progress" style="display:none;">
                <div class="progress-bar" role="progressbar" style="width:0%"></div>
            </div>`;

        this.previewContainer.appendChild(card);

        card.querySelector('.preview-remove').addEventListener('click', e => {
            e.stopPropagation();
            this._removeFile(index);
        });

        // Generate thumbnail for images
        if (!isVideo) {
            const imgContainer = card.querySelector('.preview-image-container');
            const reader = new FileReader();
            reader.onload = ev => {
                const img   = new Image();
                img.onload  = () => {
                    const canvas = document.createElement('canvas');
                    const max    = 100;
                    let   w = img.width, h = img.height;
                    if (w > h) { if (w > max) { h = h * max / w; w = max; } }
                    else       { if (h > max) { w = w * max / h; h = max; } }
                    canvas.width = w; canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    imgContainer.innerHTML = `<img src="${canvas.toDataURL('image/jpeg', 0.7)}" alt="">`;
                };
                img.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    _removeFile(index) {
        const card = this.previewContainer.querySelector(`[data-index="${index}"]`);
        if (card) card.remove();
        this.files[index] = null;
        this._updateUploadBtn();
    }

    _setCardStatus(index, statusClass, text) {
        const card = this.previewContainer.querySelector(`[data-index="${index}"]`);
        if (!card) return;
        const badge = card.querySelector('.preview-status .badge');
        if (badge) {
            badge.className = 'badge ' + statusClass;
            badge.textContent = text;
        }
    }

    _setCardProgress(index, pct) {
        const card = this.previewContainer.querySelector(`[data-index="${index}"]`);
        if (!card) return;
        const wrap = card.querySelector('.preview-progress');
        const bar  = card.querySelector('.progress-bar');
        if (wrap) wrap.style.display = 'block';
        if (bar)  bar.style.width    = pct + '%';
    }

    _updateUploadBtn() {
        const count = this.files.filter(Boolean).length;
        if (!this.uploadButton) return;
        this.uploadButton.disabled   = count === 0 || this.isUploading;
        this.uploadButton.innerHTML  = count > 0
            ? `<i class="fas fa-upload"></i> Upload ${count} File${count > 1 ? 's' : ''}`
            : '<i class="fas fa-upload"></i> Upload File(s)';
    }

    // ── Duplicate detection ──────────────────────────────────────────────────

    async _checkDuplicates(fileNames) {
        const formData = new FormData();
        formData.append('folder_id', this.options.folderId);
        fileNames.forEach(n => formData.append('file_names[]', n));

        try {
            const resp = await fetch(this.options.checkDuplicatesUrl, {
                method: 'POST',
                body:   formData,
            });
            const data = await resp.json();
            return data.success ? data.duplicates : [];
        } catch {
            return [];
        }
    }

    /**
     * Show a modal/confirm dialog for each duplicate.
     * Returns a Map<filename, 'replace'|'skip'>.
     */
    async _resolveDuplicates(duplicates) {
        const decisions = new Map();

        // Build a batch decision dialog using SweetAlert2 if available
        if (duplicates.length === 0) return decisions;

        const list = duplicates.map(n => `<li><strong>${this._escapeHtml(n)}</strong></li>`).join('');

        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title:             'Duplicate Files Found',
                icon:              'warning',
                html: `<p>The following file${duplicates.length > 1 ? 's are' : ' is'} already uploaded in this folder:</p>
                       <ul style="text-align:left;max-height:200px;overflow-y:auto;">${list}</ul>
                       <p>What would you like to do with these files?</p>`,
                showDenyButton:    true,
                showCancelButton:  true,
                confirmButtonText: '<i class="fas fa-sync-alt"></i> Replace All',
                denyButtonText:    '<i class="fas fa-forward"></i> Skip All',
                cancelButtonText:  'Cancel Upload',
                confirmButtonColor: '#dc3545',
                denyButtonColor:    '#6c757d',
            });

            if (result.isConfirmed) {
                duplicates.forEach(n => decisions.set(n, 'replace'));
            } else if (result.isDenied) {
                duplicates.forEach(n => decisions.set(n, 'skip'));
            } else {
                // User cancelled – mark all as skip so the whole batch is effectively cancelled
                duplicates.forEach(n => decisions.set(n, 'cancel'));
            }
        } else {
            // Fallback: native confirm
            const replace = confirm(
                duplicates.length + ' file(s) already exist:\n' +
                duplicates.join('\n') +
                '\n\nClick OK to replace, Cancel to skip them.'
            );
            duplicates.forEach(n => decisions.set(n, replace ? 'replace' : 'skip'));
        }

        return decisions;
    }

    // ── Resume detection ─────────────────────────────────────────────────────

    async _checkResume() {
        let pending;
        try {
            pending = await this._getPendingSessions();
        } catch {
            return;
        }

        if (pending.length === 0) return;

        const names = pending.map(s => s.originalName).join(', ');
        let resume  = false;

        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title:             'Resume Incomplete Uploads?',
                icon:              'question',
                html:              `<p>Found <strong>${pending.length}</strong> incomplete upload${pending.length > 1 ? 's' : ''}:</p>
                                    <p><em>${this._escapeHtml(names)}</em></p>
                                    <p>Would you like to resume?</p>`,
                showCancelButton:  true,
                confirmButtonText: '<i class="fas fa-play"></i> Resume',
                cancelButtonText:  'Discard',
                confirmButtonColor: '#28a745',
            });
            resume = result.isConfirmed;
        } else {
            resume = confirm(
                'Found ' + pending.length + ' incomplete upload(s): ' + names +
                '\n\nResume? Click Cancel to discard.'
            );
        }

        if (resume) {
            this._runResume(pending);
        } else {
            // Discard stale sessions
            for (const s of pending) {
                await this._deleteSession(s.sessionId);
            }
        }
    }

    async _runResume(sessions) {
        this.isUploading = true;
        this._updateUploadBtn();

        for (const session of sessions) {
            // Create a fake preview card for the resumed file
            const fakeIndex = this.files.length;
            this.files.push(null); // placeholder

            const card = document.createElement('div');
            card.className     = 'image-preview-item';
            card.dataset.index = fakeIndex;
            card.innerHTML = `
                <div class="preview-image-container">
                    <i class="fas fa-${session.isVideo ? 'video' : 'image'} fa-2x text-muted"></i>
                </div>
                <div class="preview-info">
                    <div class="preview-name">${this._truncateName(session.originalName)}</div>
                    <div class="preview-size">${this._formatSize(session.fileSize)}</div>
                    <div class="preview-status"><span class="badge bg-warning">Resuming…</span></div>
                </div>
                <div class="preview-progress">
                    <div class="progress-bar" role="progressbar" style="width:0%"></div>
                </div>`;
            this.previewContainer.appendChild(card);

            // We no longer have the File object; we need to ask the user to re-select
            // the same file.  Show a prompt explaining this.
            this._setCardStatus(fakeIndex, 'bg-warning text-dark', 'Select file to resume');

            const fileHandle = await this._askForFile(session.originalName, fakeIndex);
            if (!fileHandle) {
                this._setCardStatus(fakeIndex, 'bg-secondary', 'Skipped');
                await this._deleteSession(session.sessionId);
                continue;
            }

            try {
                await this._uploadFile(fileHandle, fakeIndex, session.sessionId, session.chunksReceived);
                await this._deleteSession(session.sessionId);
            } catch (err) {
                this._setCardStatus(fakeIndex, 'bg-danger', 'Failed');
            }
        }

        this.isUploading = false;
        this._updateUploadBtn();
    }

    /** Prompt the user to pick a specific file by name via a temporary input. */
    _askForFile(fileName, index) {
        return new Promise(resolve => {
            if (typeof Swal === 'undefined') {
                resolve(null);
                return;
            }

            Swal.fire({
                title:             'Re-select File to Resume',
                html:              `<p>To resume the upload of <strong>${this._escapeHtml(fileName)}</strong>, please select the same file again.</p>
                                    <input type="file" id="resumeFileInput" style="width:100%">`,
                showCancelButton:  true,
                confirmButtonText: 'Resume',
                cancelButtonText:  'Skip',
                preConfirm: () => {
                    const f = document.getElementById('resumeFileInput').files[0];
                    if (!f) {
                        Swal.showValidationMessage('Please select a file.');
                        return false;
                    }
                    return f;
                },
            }).then(result => {
                resolve(result.isConfirmed ? result.value : null);
            });
        });
    }

    // ── Upload flow ──────────────────────────────────────────────────────────

    async _handleSubmit(e) {
        e.preventDefault();

        const active = this.files.filter(Boolean);
        if (active.length === 0) {
            this._alert('error', 'Please select at least one file to upload.');
            return;
        }

        this.isUploading = true;
        this._updateUploadBtn();

        // 1. Check for duplicates
        const fileNames    = active.map(f => f.name);
        const duplicates   = await this._checkDuplicates(fileNames);
        const decisions    = await this._resolveDuplicates(duplicates);

        // If user cancelled, abort everything
        if ([...decisions.values()].includes('cancel')) {
            this.isUploading = false;
            this._updateUploadBtn();
            return;
        }

        let uploaded = 0;
        let errors   = 0;

        for (let i = 0; i < this.files.length; i++) {
            const file = this.files[i];
            if (!file) continue;

            const decision = decisions.get(file.name);
            if (decision === 'skip') {
                this._setCardStatus(i, 'bg-secondary', 'Skipped');
                continue;
            }

            this._setCardStatus(i, 'bg-info', 'Uploading…');

            try {
                const sessionId  = this._generateSessionId();
                await this._uploadFile(file, i, sessionId, []);
                uploaded++;
            } catch (err) {
                console.error('Upload error:', err);
                errors++;
                this._setCardStatus(i, 'bg-danger', 'Failed');
                this._alert('error', `Failed to upload ${this._escapeHtml(file.name)}: ${err.message}`);
            }
        }

        this.isUploading = false;
        this._updateUploadBtn();

        if (uploaded > 0) {
            this._alert(
                'success',
                `${uploaded} file${uploaded > 1 ? 's' : ''} uploaded successfully!`
            );
        }

        this.options.onUploadComplete({ uploadedCount: uploaded, errorCount: errors });

        if (uploaded > 0 && errors === 0) {
            setTimeout(() => this._clearFiles(), 2000);
        }
    }

    /**
     * Upload a single file using chunked upload.
     * @param {File}   file             - the File object to upload
     * @param {number} previewIndex     - index into this.files for UI updates
     * @param {string} sessionId        - 64-char hex session ID
     * @param {Array}  alreadyReceived  - chunk indices already confirmed by the server
     */
    async _uploadFile(file, previewIndex, sessionId, alreadyReceived) {
        const chunks      = this._sliceFile(file);
        const totalChunks = chunks.length;
        const received    = new Set(alreadyReceived);

        // Persist session to IndexedDB so it can be resumed
        const sessionData = {
            sessionId,
            folderId:      this.options.folderId,
            originalName:  file.name,
            fileSize:      file.size,
            totalChunks,
            chunksReceived: [...received],
            isVideo:       this.isVideoFile(file),
            status:        'uploading',
        };
        await this._saveSession(sessionData);

        for (let idx = 0; idx < totalChunks; idx++) {
            if (received.has(idx)) {
                // Already uploaded (resume path)
                const pct = Math.round(((idx + 1) / totalChunks) * 100);
                this._setCardProgress(previewIndex, pct);
                continue;
            }

            let attempt = 0;
            let success = false;

            while (attempt < ResumableUploadHandler.MAX_RETRIES && !success) {
                try {
                    const resp = await this._sendChunk(sessionId, idx, chunks[idx], file, totalChunks);
                    if (!resp.success) throw new Error(resp.message || 'Server error');

                    received.add(idx);
                    success = true;

                    // Update persisted progress
                    sessionData.chunksReceived = [...received];
                    await this._saveSession(sessionData);

                    const pct = Math.round(((received.size) / totalChunks) * 100);
                    this._setCardProgress(previewIndex, pct);
                } catch (err) {
                    attempt++;
                    if (attempt < ResumableUploadHandler.MAX_RETRIES) {
                        this._setCardStatus(previewIndex, 'bg-warning text-dark', `Retrying (${attempt})…`);
                        await this._sleep(ResumableUploadHandler.RETRY_DELAY);
                    } else {
                        throw err;
                    }
                }
            }
        }

        // All chunks sent – ask server to assemble
        this._setCardStatus(previewIndex, 'bg-info', 'Assembling…');
        const completeResp = await this._completeUpload(sessionId);

        if (!completeResp.success) {
            throw new Error(completeResp.message || 'Assembly failed');
        }

        // Clean up local session record
        sessionData.status = 'complete';
        await this._saveSession(sessionData);
        await this._deleteSession(sessionId);

        this._setCardProgress(previewIndex, 100);
        this._setCardStatus(previewIndex, 'bg-success', 'Uploaded');

        return completeResp;
    }

    _sendChunk(sessionId, chunkIndex, chunk, file, totalChunks) {
        return new Promise((resolve, reject) => {
            const fd = new FormData();
            fd.append('session_id',    sessionId);
            fd.append('folder_id',     this.options.folderId);
            fd.append('original_name', file.name);
            fd.append('file_size',     file.size);
            fd.append('total_chunks',  totalChunks);
            fd.append('chunk_index',   chunkIndex);
            fd.append('chunk',         chunk, file.name);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', this.options.uploadChunkUrl);
            xhr.onload = () => {
                try   { resolve(JSON.parse(xhr.responseText)); }
                catch { reject(new Error('Invalid server response')); }
            };
            xhr.onerror = ()  => reject(new Error('Network error'));
            xhr.send(fd);
        });
    }

    async _completeUpload(sessionId) {
        const fd = new FormData();
        fd.append('session_id', sessionId);
        fd.append('folder_id',  this.options.folderId);

        try {
            const resp = await fetch(this.options.completeUploadUrl, { method: 'POST', body: fd });
            return await resp.json();
        } catch {
            return { success: false, message: 'Network error during assembly.' };
        }
    }

    // ── Misc ─────────────────────────────────────────────────────────────────

    _clearFiles() {
        this.files = [];
        if (this.previewContainer) this.previewContainer.innerHTML = '';
        if (this.fileInput)        this.fileInput.value            = '';
        this._updateUploadBtn();
    }

    _alert(type, html) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon:               type,
                title:              type === 'success' ? 'Success!' : 'Error',
                html,
                confirmButtonColor: '#4CAF50',
            });
        } else {
            alert((type === 'error' ? 'Error: ' : '') + html.replace(/<[^>]*>/g, ''));
        }
    }

    // ── Initialisation ───────────────────────────────────────────────────────

    _init() {
        this._initUI();

        // Check for resumable sessions after the DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this._checkResume());
        } else {
            this._checkResume();
        }
    }
}
