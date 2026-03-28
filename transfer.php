<?php
/**
 * Public File Transfer Page
 * TransferNow-like interface: upload files, get a shareable download link.
 * No login required.
 */

$page_title = 'Send Files';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$site_name = getSetting('site_name', 'Venue Booking System');
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Files – <?php echo htmlspecialchars($site_name); ?></title>
    <meta name="description" content="Upload and share files easily. No account needed.">

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --brand:       #4f46e5;
            --brand-dark:  #3730a3;
            --brand-light: #eef2ff;
            --success:     #22c55e;
            --error:       #ef4444;
            --warning:     #f59e0b;
            --bg:          #f8fafc;
            --card-bg:     #ffffff;
            --text:        #1e293b;
            --muted:       #64748b;
            --border:      #e2e8f0;
            --radius:      12px;
            --shadow:      0 4px 24px rgba(0,0,0,.08);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Top navigation ───────────────────────────────── */
        .top-nav {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .top-nav .brand {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--brand);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .top-nav .brand i { font-size: 1.3rem; }
        .top-nav .nav-link {
            color: var(--muted);
            text-decoration: none;
            font-size: .9rem;
            font-weight: 500;
            transition: color .15s;
        }
        .top-nav .nav-link:hover { color: var(--brand); }

        /* ── Hero section ────────────────────────────────── */
        .hero {
            text-align: center;
            padding: 48px 24px 24px;
        }
        .hero h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 10px;
        }
        .hero h1 span { color: var(--brand); }
        .hero p {
            color: var(--muted);
            font-size: 1.05rem;
            max-width: 480px;
            margin: 0 auto;
        }

        /* ── Main card ───────────────────────────────────── */
        .main-wrap {
            flex: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 16px 16px 48px;
        }
        .transfer-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 680px;
            overflow: hidden;
        }

        /* ── Drop zone ───────────────────────────────────── */
        .drop-zone {
            border: 2px dashed var(--border);
            border-radius: 10px;
            margin: 24px;
            padding: 48px 24px;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            position: relative;
        }
        .drop-zone.drag-over {
            border-color: var(--brand);
            background: var(--brand-light);
        }
        .drop-zone input[type=file] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .drop-zone .drop-icon {
            font-size: 3rem;
            color: var(--brand);
            margin-bottom: 16px;
        }
        .drop-zone h3 {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .drop-zone p {
            color: var(--muted);
            font-size: .9rem;
        }
        .drop-zone .browse-btn {
            display: inline-block;
            margin-top: 14px;
            padding: 9px 22px;
            background: var(--brand);
            color: #fff;
            border-radius: 8px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            pointer-events: none; /* click handled by the <input> */
        }

        /* ── File list ───────────────────────────────────── */
        .file-list {
            margin: 0 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .file-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            background: var(--bg);
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .file-item .file-icon {
            font-size: 1.5rem;
            width: 36px;
            text-align: center;
            flex-shrink: 0;
        }
        .file-item .file-icon.photo { color: #22d3ee; }
        .file-item .file-icon.video { color: #f59e0b; }
        .file-item .file-icon.file  { color: #6366f1; }
        .file-item .file-info { flex: 1; min-width: 0; }
        .file-item .file-name {
            font-size: .9rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .file-item .file-meta {
            font-size: .78rem;
            color: var(--muted);
            margin-top: 2px;
        }
        .file-item .file-progress-wrap {
            margin-top: 5px;
            height: 4px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
        }
        .file-item .file-progress-bar {
            height: 100%;
            background: var(--brand);
            border-radius: 4px;
            width: 0;
            transition: width .1s linear;
        }
        .file-item .file-status {
            font-size: .75rem;
            font-weight: 600;
            flex-shrink: 0;
            min-width: 60px;
            text-align: right;
        }
        .file-item .file-status.pending  { color: var(--muted); }
        .file-item .file-status.uploading { color: var(--brand); }
        .file-item .file-status.done    { color: var(--success); }
        .file-item .file-status.error   { color: var(--error); }
        .file-item .remove-btn {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 1rem;
            padding: 2px 4px;
            border-radius: 4px;
            transition: color .15s;
            flex-shrink: 0;
        }
        .file-item .remove-btn:hover { color: var(--error); }

        /* ── Options ─────────────────────────────────────── */
        .options-section {
            margin: 16px 24px 0;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }
        @media (max-width: 520px) {
            .options-grid { grid-template-columns: 1fr; }
        }
        .form-group label {
            display: block;
            font-size: .82rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: .9rem;
            font-family: inherit;
            background: var(--bg);
            color: var(--text);
            outline: none;
            transition: border-color .15s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--brand);
            background: #fff;
        }
        .form-group textarea { resize: vertical; min-height: 72px; }

        /* ── Send button area ────────────────────────────── */
        .send-area {
            padding: 20px 24px 24px;
        }
        .btn-send {
            width: 100%;
            padding: 14px;
            background: var(--brand);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background .2s, transform .1s;
        }
        .btn-send:hover:not(:disabled) { background: var(--brand-dark); }
        .btn-send:active:not(:disabled) { transform: scale(.98); }
        .btn-send:disabled { opacity: .55; cursor: not-allowed; }
        .btn-send .spinner {
            width: 18px; height: 18px;
            border: 3px solid rgba(255,255,255,.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: none;
        }
        .btn-send.loading .spinner { display: block; }
        .btn-send.loading .btn-text { display: none; }

        /* ── Success banner ──────────────────────────────── */
        .success-banner {
            display: none;
            margin: 0 24px 24px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        .success-banner .success-icon {
            font-size: 2.5rem;
            color: var(--success);
            margin-bottom: 10px;
        }
        .success-banner h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #166534;
            margin-bottom: 6px;
        }
        .success-banner p {
            font-size: .88rem;
            color: #166534;
            margin-bottom: 14px;
        }
        .link-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 10px 12px;
            overflow: hidden;
        }
        .link-box input {
            flex: 1;
            border: none;
            outline: none;
            font-size: .88rem;
            color: var(--text);
            background: transparent;
            font-family: monospace;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .link-box button {
            background: var(--brand);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            flex-shrink: 0;
            transition: background .15s;
        }
        .link-box button:hover { background: var(--brand-dark); }
        .link-box button.copied { background: var(--success); }
        .expire-note {
            font-size: .8rem;
            color: var(--muted);
            margin-top: 8px;
        }
        .btn-new-transfer {
            margin-top: 14px;
            padding: 9px 22px;
            background: transparent;
            border: 2px solid var(--brand);
            color: var(--brand);
            border-radius: 8px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s, color .15s;
        }
        .btn-new-transfer:hover {
            background: var(--brand);
            color: #fff;
        }

        /* ── Info bar ────────────────────────────────────── */
        .info-bar {
            display: flex;
            justify-content: center;
            gap: 32px;
            padding: 28px 24px;
            flex-wrap: wrap;
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--muted);
            font-size: .88rem;
        }
        .info-item i { font-size: 1.2rem; color: var(--brand); }

        /* ── Error message ───────────────────────────────── */
        .error-msg {
            display: none;
            margin: 0 24px 12px;
            padding: 10px 14px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            color: var(--error);
            font-size: .88rem;
        }

        /* ── Footer ──────────────────────────────────────── */
        footer {
            text-align: center;
            padding: 20px;
            color: var(--muted);
            font-size: .82rem;
            border-top: 1px solid var(--border);
        }
        footer a { color: var(--brand); text-decoration: none; }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ── Responsive ──────────────────────────────────── */
        @media (max-width: 480px) {
            .hero h1 { font-size: 1.6rem; }
            .drop-zone { padding: 32px 16px; }
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="top-nav">
    <a class="brand" href="<?php echo BASE_URL; ?>/">
        <i class="fas fa-cloud-upload-alt"></i>
        <?php echo htmlspecialchars($site_name); ?>
    </a>
    <a class="nav-link" href="<?php echo BASE_URL; ?>/">
        <i class="fas fa-arrow-left"></i> Back to home
    </a>
</nav>

<!-- Hero -->
<div class="hero">
    <h1>Send files <span>for free</span></h1>
    <p>Upload files, share the link. Files expire automatically. No account needed.</p>
</div>

<!-- Main card -->
<div class="main-wrap">
    <div class="transfer-card">

        <!-- Drop zone (hidden after upload completes) -->
        <div id="uploadArea">
            <div class="drop-zone" id="dropZone">
                <input type="file" id="fileInput" multiple>
                <div class="drop-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                <h3>Drag &amp; drop files here</h3>
                <p>or click to browse your device</p>
                <span class="browse-btn"><i class="fas fa-folder-open"></i> Choose Files</span>
            </div>

            <!-- File list -->
            <div class="file-list" id="fileList"></div>

            <!-- Error -->
            <div class="error-msg" id="errorMsg"></div>

            <!-- Options -->
            <div class="options-section" id="optionsSection" style="display:none">
                <div class="options-grid">
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Expires in</label>
                        <select id="expiryDays">
                            <option value="1">1 day</option>
                            <option value="3">3 days</option>
                            <option value="7" selected>7 days</option>
                            <option value="14">14 days</option>
                            <option value="30">30 days</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Your email (optional)</label>
                        <input type="email" id="senderEmail" placeholder="your@email.com">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label><i class="fas fa-comment-alt"></i> Message (optional)</label>
                    <textarea id="senderMessage" placeholder="Add a message for the recipient…"></textarea>
                </div>
            </div>

            <!-- Send button -->
            <div class="send-area">
                <button class="btn-send" id="sendBtn" disabled>
                    <span class="btn-text"><i class="fas fa-paper-plane"></i> Transfer Files</span>
                    <div class="spinner"></div>
                </button>
            </div>
        </div><!-- /#uploadArea -->

        <!-- Success banner -->
        <div class="success-banner" id="successBanner">
            <div class="success-icon"><i class="fas fa-check-circle"></i></div>
            <h3>Transfer complete!</h3>
            <p>Share this link with your recipients:</p>
            <div class="link-box">
                <input type="text" id="shareLink" readonly>
                <button id="copyBtn" onclick="copyLink()">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
            <p class="expire-note" id="expireNote"></p>
            <button class="btn-new-transfer" onclick="startNewTransfer()">
                <i class="fas fa-plus"></i> New transfer
            </button>
        </div>

    </div>
</div>

<!-- Info bar -->
<div class="info-bar">
    <div class="info-item">
        <i class="fas fa-lock"></i>
        <span>Secure upload</span>
    </div>
    <div class="info-item">
        <i class="fas fa-infinity"></i>
        <span>Any file type</span>
    </div>
    <div class="info-item">
        <i class="fas fa-clock"></i>
        <span>Auto-expires</span>
    </div>
    <div class="info-item">
        <i class="fas fa-user-slash"></i>
        <span>No account needed</span>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date('Y'); ?> <a href="<?php echo BASE_URL; ?>/"><?php echo htmlspecialchars($site_name); ?></a>. File transfer service.</p>
</footer>

<script>
(function () {
    'use strict';

    // ── Constants ──────────────────────────────────────────────
    const BASE_URL   = <?php echo json_encode(BASE_URL); ?>;
    const CSRF_TOKEN = <?php echo json_encode($csrf_token); ?>;
    const CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB

    // ── State ──────────────────────────────────────────────────
    let files        = [];   // { file, status, progress, uploadId }
    let folderId     = null;
    let folderToken  = null;
    let uploading    = false;
    let expiryDays   = 7;

    // ── DOM refs ────────────────────────────────────────────────
    const dropZone       = document.getElementById('dropZone');
    const fileInput      = document.getElementById('fileInput');
    const fileListEl     = document.getElementById('fileList');
    const sendBtn        = document.getElementById('sendBtn');
    const errorMsg       = document.getElementById('errorMsg');
    const optionsSection = document.getElementById('optionsSection');
    const successBanner  = document.getElementById('successBanner');
    const uploadArea     = document.getElementById('uploadArea');
    const shareLinkEl    = document.getElementById('shareLink');
    const expireNote     = document.getElementById('expireNote');

    // ── Drag & drop ─────────────────────────────────────────────
    dropZone.addEventListener('dragover', e => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    ['dragleave', 'dragend'].forEach(ev =>
        dropZone.addEventListener(ev, () => dropZone.classList.remove('drag-over'))
    );
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        addFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', () => {
        addFiles(fileInput.files);
        fileInput.value = ''; // allow re-selecting same file
    });

    // ── Add files ────────────────────────────────────────────────
    function addFiles(fileList) {
        if (uploading) return;
        Array.from(fileList).forEach(f => {
            if (files.some(x => x.file.name === f.name && x.file.size === f.size)) return;
            files.push({ file: f, status: 'pending', progress: 0, uploadId: generateId() });
        });
        renderList();
    }

    function generateId() {
        return 'tid-' + Math.random().toString(36).substr(2, 10) + '-' + Date.now().toString(36);
    }

    // ── Render file list ─────────────────────────────────────────
    function renderList() {
        fileListEl.innerHTML = '';
        files.forEach((item, idx) => {
            const ext = item.file.name.split('.').pop().toLowerCase();
            const iconClass = isPhoto(ext) ? 'photo' : (isVideo(ext) ? 'video' : 'file');
            const iconName  = isPhoto(ext) ? 'fa-image' : (isVideo(ext) ? 'fa-film' : 'fa-file');

            const div = document.createElement('div');
            div.className = 'file-item';
            div.innerHTML = `
                <div class="file-icon ${iconClass}"><i class="fas ${iconName}"></i></div>
                <div class="file-info">
                    <div class="file-name">${escHtml(item.file.name)}</div>
                    <div class="file-meta">${formatSize(item.file.size)}</div>
                    <div class="file-progress-wrap" style="display:${item.status === 'uploading' ? 'block' : 'none'}">
                        <div class="file-progress-bar" style="width:${item.progress}%"></div>
                    </div>
                </div>
                <span class="file-status ${item.status}">${statusLabel(item.status, item.progress)}</span>
                ${item.status === 'pending' ? `<button class="remove-btn" data-idx="${idx}" title="Remove"><i class="fas fa-times"></i></button>` : ''}
            `;
            fileListEl.appendChild(div);
        });

        // Remove buttons
        fileListEl.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const i = parseInt(btn.dataset.idx);
                files.splice(i, 1);
                renderList();
            });
        });

        const hasFiles = files.length > 0;
        optionsSection.style.display = hasFiles ? 'block' : 'none';
        sendBtn.disabled = !hasFiles || uploading;
    }

    function statusLabel(status, progress) {
        if (status === 'pending')   return 'Queued';
        if (status === 'uploading') return progress + '%';
        if (status === 'done')      return '✓ Done';
        if (status === 'error')     return '✗ Error';
        return '';
    }

    // ── Upload ────────────────────────────────────────────────────
    sendBtn.addEventListener('click', startUpload);

    async function startUpload() {
        if (uploading || files.length === 0) return;
        uploading = true;
        folderId  = null;
        folderToken = null;

        expiryDays = parseInt(document.getElementById('expiryDays').value) || 7;
        hideError();
        sendBtn.classList.add('loading');
        sendBtn.disabled = true;

        let hasError = false;

        for (let i = 0; i < files.length; i++) {
            const item = files[i];
            if (item.status === 'done') continue;
            try {
                await uploadFile(item, i);
            } catch (err) {
                item.status = 'error';
                hasError = true;
                renderList();
            }
        }

        sendBtn.classList.remove('loading');
        uploading = false;

        if (folderToken) {
            // BASE_URL already contains the full origin (scheme + host + path) when
            // auto-detected, so we must NOT prepend window.location.origin again.
            // When BASE_URL is a plain path (legacy / fallback), prepend origin.
            const baseUrl = BASE_URL.startsWith('http') ? BASE_URL
                          : window.location.origin.replace(/\/$/, '') + BASE_URL;
            const link = baseUrl + '/folder.php?token=' + encodeURIComponent(folderToken);
            shareLinkEl.value = link;
            expireNote.textContent = 'This link expires in ' + expiryDays + ' day' + (expiryDays !== 1 ? 's' : '') + '.';
            uploadArea.style.display = 'none';
            successBanner.style.display = 'block';
        } else if (hasError) {
            showError('Some files failed to upload. Please try again.');
            sendBtn.disabled = false;
        }
    }

    async function uploadFile(item, idx) {
        const f = item.file;
        const useChunks = f.size > CHUNK_SIZE;

        if (useChunks) {
            await uploadChunked(item, idx);
        } else {
            await uploadStandard(item, idx);
        }
    }

    // Standard upload (files ≤ 5 MB)
    function uploadStandard(item, idx) {
        return new Promise((resolve, reject) => {
            item.status = 'uploading';
            renderList();

            const fd = new FormData();
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('files', item.file, item.file.name);
            fd.append('expiry_days',     expiryDays);
            fd.append('sender_email',    document.getElementById('senderEmail').value.trim());
            fd.append('sender_message',  document.getElementById('senderMessage').value.trim());
            if (folderId) fd.append('folder_id', folderId);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', BASE_URL + '/api/upload-transfer.php', true);

            xhr.upload.onprogress = e => {
                if (e.lengthComputable) {
                    item.progress = Math.round(e.loaded / e.total * 100);
                    renderList();
                }
            };

            xhr.onload = () => {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        item.status   = 'done';
                        item.progress = 100;
                        if (!folderId)    folderId    = res.folder_id;
                        if (!folderToken) folderToken = res.folder_token;
                        renderList();
                        resolve(res);
                    } else {
                        item.status = 'error';
                        renderList();
                        reject(new Error(res.message || 'Upload failed.'));
                    }
                } catch (e) {
                    item.status = 'error';
                    renderList();
                    reject(e);
                }
            };

            xhr.onerror = () => {
                item.status = 'error';
                renderList();
                reject(new Error('Network error.'));
            };

            xhr.send(fd);
        });
    }

    // Chunked upload (files > 5 MB)
    async function uploadChunked(item, idx) {
        const f           = item.file;
        const totalChunks = Math.ceil(f.size / CHUNK_SIZE);
        item.status       = 'uploading';
        renderList();

        for (let ci = 0; ci < totalChunks; ci++) {
            const start = ci * CHUNK_SIZE;
            const end   = Math.min(start + CHUNK_SIZE, f.size);
            const chunk = f.slice(start, end);

            const fd = new FormData();
            fd.append('csrf_token',    CSRF_TOKEN);
            fd.append('chunk',         chunk, 'chunk');
            fd.append('chunk_index',   ci);
            fd.append('total_chunks',  totalChunks);
            fd.append('upload_id',     item.uploadId);
            fd.append('original_name', f.name);
            fd.append('expiry_days',   expiryDays);
            fd.append('sender_email',  document.getElementById('senderEmail').value.trim());
            fd.append('sender_message', document.getElementById('senderMessage').value.trim());
            if (folderId) fd.append('folder_id', folderId);

            const res = await postJson(BASE_URL + '/api/chunk-transfer.php', fd, pct => {
                item.progress = Math.round(((ci + pct / 100) / totalChunks) * 100);
                renderList();
            });

            if (!res.success) {
                item.status = 'error';
                renderList();
                throw new Error(res.message || 'Chunk upload failed.');
            }

            if (!folderId)    folderId    = res.folder_id;
            if (!folderToken) folderToken = res.folder_token || folderToken;
        }

        item.status   = 'done';
        item.progress = 100;
        renderList();
    }

    function postJson(url, fd, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            if (onProgress) {
                xhr.upload.onprogress = e => {
                    if (e.lengthComputable) onProgress(Math.round(e.loaded / e.total * 100));
                };
            }
            xhr.onload = () => {
                try { resolve(JSON.parse(xhr.responseText)); }
                catch (e) { reject(e); }
            };
            xhr.onerror = () => reject(new Error('Network error.'));
            xhr.send(fd);
        });
    }

    // ── Copy link ─────────────────────────────────────────────────
    window.copyLink = function () {
        const btn = document.getElementById('copyBtn');
        shareLinkEl.select();
        shareLinkEl.setSelectionRange(0, 99999);
        try {
            document.execCommand('copy');
        } catch (e) {
            navigator.clipboard?.writeText(shareLinkEl.value);
        }
        btn.textContent = '✓ Copied!';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-copy"></i> Copy';
            btn.classList.remove('copied');
        }, 2500);
    };

    // ── New transfer ─────────────────────────────────────────────
    window.startNewTransfer = function () {
        files       = [];
        folderId    = null;
        folderToken = null;
        uploading   = false;
        document.getElementById('senderEmail').value   = '';
        document.getElementById('senderMessage').value = '';
        fileListEl.innerHTML = '';
        optionsSection.style.display = 'none';
        sendBtn.disabled             = true;
        successBanner.style.display  = 'none';
        uploadArea.style.display     = 'block';
    };

    // ── Helpers ───────────────────────────────────────────────────
    function showError(msg) {
        errorMsg.textContent    = msg;
        errorMsg.style.display  = 'block';
    }
    function hideError() {
        errorMsg.style.display  = 'none';
    }

    function formatSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024, sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                  .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function isPhoto(ext) {
        return ['jpg','jpeg','png','gif','webp','bmp','svg','tiff','ico'].includes(ext);
    }
    function isVideo(ext) {
        return ['mp4','mov','avi','webm','mkv','mpg','mpeg','3gp','flv','wmv'].includes(ext);
    }
})();
</script>
</body>
</html>
