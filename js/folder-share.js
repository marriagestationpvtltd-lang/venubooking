/* folder-share.js – extracted from folder.php */

var _selectMode = false;

/* Downloadable files list is set inline by folder.php before this script loads:
   var _dlFiles = [...];  window._folderToken = '...'; */

function handleImageError(img) {
    var placeholder = document.createElement('div');
    placeholder.className = 'img-error-placeholder';
    placeholder.innerHTML = '<i class="fas fa-image"></i><span>Image unavailable</span>';
    img.parentNode.replaceChild(placeholder, img);
}

function openLightbox(src, downloadUrl, title) {
    document.getElementById('lightbox-image').src = src;
    var dlBtn = document.getElementById('lightbox-download-btn');
    if (dlBtn) {
        if (downloadUrl) {
            dlBtn.href = downloadUrl;
            dlBtn.classList.add('visible');
            dlBtn.onclick = function(e) {
                e.stopPropagation();
                singlePhotoDownload(downloadUrl, title || '');
                return false;
            };
        } else {
            dlBtn.classList.remove('visible');
        }
    }
    document.getElementById('lightbox').classList.add('active');
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
}

function openVideoLightbox(src, downloadUrl, title) {
    var video = document.getElementById('lightbox-video');
    var sourceEl = document.getElementById('lightbox-video-src');
    var ext = src.split('?')[0].split('.').pop().toLowerCase();
    var mimeMap = {
        'mp4': 'video/mp4', 'mov': 'video/quicktime', 'm4v': 'video/mp4',
        'webm': 'video/webm', 'ogg': 'video/ogg', 'ogv': 'video/ogg',
        'avi': 'video/x-msvideo', 'mkv': 'video/x-matroska',
        'mpg': 'video/mpeg', 'mpeg': 'video/mpeg', '3gp': 'video/3gpp'
    };
    video.pause();
    sourceEl.src = src;
    sourceEl.type = mimeMap[ext] || 'video/mp4';
    video.load();
    var dlBtn = document.getElementById('video-lightbox-download-btn');
    if (dlBtn) {
        if (downloadUrl) {
            dlBtn.href = downloadUrl;
            dlBtn.classList.add('visible');
            dlBtn.onclick = function(e) {
                e.stopPropagation();
                singlePhotoDownload(downloadUrl, title || '');
                return false;
            };
        } else {
            dlBtn.classList.remove('visible');
        }
    }
    document.getElementById('video-lightbox').classList.add('active');
}

function closeVideoLightbox() {
    var video = document.getElementById('lightbox-video');
    video.pause();
    video.currentTime = 0;
    document.getElementById('video-lightbox').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLightbox();
        closeVideoLightbox();
    }
});

async function startDownload(url, defaultName) {
    var overlay = document.getElementById('downloadProgressOverlay');
    var dlBar   = document.getElementById('dlBar');
    var dlPct   = document.getElementById('dlPercent');
    var dlEta   = document.getElementById('dlEta');
    var dlSpd   = document.getElementById('dlSpeed');
    var dlTitle = document.getElementById('dlTitle');
    var dlFile  = document.getElementById('dlFilename');
    var dlSize  = document.getElementById('dlSizeInfo');
    var dlIcon  = document.getElementById('dlIcon');

    function showSuccess(savedMsg) {
        dlTitle.textContent = 'Download Complete!';
        dlSize.textContent  = savedMsg || 'File saved successfully';
        dlIcon.className    = 'fas fa-check-circle';
        setTimeout(function() {
            overlay.classList.remove('dl-active');
        }, 1500);
    }

    function showError(msg) {
        dlTitle.textContent = 'Download Failed';
        dlSize.textContent  = msg || 'Please try again';
        dlIcon.className    = 'fas fa-exclamation-circle';
        dlBar.style.background = '#dc3545';
        dlBar.style.width = '100%';
        setTimeout(function() {
            overlay.classList.remove('dl-active');
        }, 3000);
    }

    dlBar.style.width      = '100%';
    dlBar.style.background = 'linear-gradient(90deg,#4CAF50,#8BC34A)';
    dlBar.style.backgroundSize = '';
    dlBar.style.animation  = '';
    dlPct.textContent      = '';
    dlEta.textContent      = '';
    dlSpd.textContent      = '';
    dlTitle.textContent    = 'Starting Download...';
    dlFile.textContent     = defaultName || '';
    dlSize.textContent     = '';
    dlIcon.className       = 'fas fa-spinner fa-spin';
    overlay.classList.add('dl-active');

    // Always create a fresh iframe to avoid stale-document false-positive errors.
    // Reusing an iframe that previously loaded an HTML error page would cause the
    // body-content check to fire even for a successful subsequent download.
    var oldFrame = document.getElementById('downloadFrame');
    if (oldFrame && oldFrame.parentNode) {
        oldFrame.parentNode.removeChild(oldFrame);
    }
    var iframe = document.createElement('iframe');
    iframe.id = 'downloadFrame';
    iframe.name = 'downloadFrame';
    iframe.style.display = 'none';
    document.body.appendChild(iframe);

    var loadTimeout = setTimeout(function() { showSuccess('Check your browser downloads'); }, 800);

    iframe.onload = function() {
        clearTimeout(loadTimeout);
        try {
            var iWin = iframe.contentWindow;
            var iDoc = iWin ? (iframe.contentDocument || iWin.document) : null;
            if (iDoc && iDoc.body && iDoc.body.innerHTML.trim() !== '') {
                showError('Download failed – please try again');
                return;
            }
        } catch (e) {}
        showSuccess('Check your browser downloads');
    };
    iframe.onerror = function() {
        clearTimeout(loadTimeout);
        showError('Connection error – please try again');
    };

    iframe.src = url;
    return false;
}

function singlePhotoDownload(url, displayName) {
    var idMatch   = (typeof url === 'string') ? url.match(/[?&]download_photo=(\d+)/) : null;
    var photoId   = idMatch ? idMatch[1] : null;
    var fileEntry = null;
    if (photoId) {
        for (var _i = 0; _i < _dlFiles.length; _i++) {
            if (_dlFiles[_i].url.indexOf('download_photo=' + photoId) !== -1) {
                fileEntry = _dlFiles[_i];
                break;
            }
        }
    }
    var filename = fileEntry ? fileEntry.filename : (displayName || 'photo');
    showDownloadConfirm(filename, 'Do you want to download this file?', function() {
        startDownload(url, filename);
    });
    return false;
}

function bulkDownloadIndividual(urls, directoryHandle) {
    if (!urls || urls.length === 0) return false;

    var overlay = document.getElementById('downloadProgressOverlay');
    var dlBar   = document.getElementById('dlBar');
    var dlPct   = document.getElementById('dlPercent');
    var dlTitle = document.getElementById('dlTitle');
    var dlFile  = document.getElementById('dlFilename');
    var dlSize  = document.getElementById('dlSizeInfo');
    var dlIcon  = document.getElementById('dlIcon');
    var dlEta   = document.getElementById('dlEta');
    var dlSpd   = document.getElementById('dlSpeed');

    var total   = urls.length;
    var current = 0;

    dlBar.style.width           = '0%';
    dlBar.style.background      = 'linear-gradient(90deg,#4CAF50,#8BC34A)';
    dlBar.style.backgroundSize  = '';
    dlBar.style.animation       = '';
    dlPct.textContent      = '0%';
    dlTitle.textContent    = 'Downloading Files\u2026';
    dlFile.textContent     = '0 of ' + total + ' file' + (total !== 1 ? 's' : '');
    dlEta.textContent      = '';
    dlSpd.textContent      = '';
    dlSize.textContent     = '';
    dlIcon.className       = 'fas fa-spinner fa-spin';
    overlay.classList.add('dl-active');

    function updateProgress() {
        var pct = Math.round((current / total) * 100);
        dlBar.style.width  = pct + '%';
        dlPct.textContent  = pct + '%';
        dlFile.textContent = current + ' of ' + total + ' file' + (total !== 1 ? 's' : '');
    }

    function showComplete(completionMsg) {
        dlBar.style.width   = '100%';
        dlPct.textContent   = '100%';
        dlTitle.textContent = 'All ' + total + ' file' + (total !== 1 ? 's' : '') + ' downloaded!';
        dlFile.textContent  = completionMsg;
        dlIcon.className    = 'fas fa-check-circle';
        setTimeout(function () { overlay.classList.remove('dl-active'); }, 2500);
    }

    var queue = urls.slice();

    if (directoryHandle) {
        var failed = 0;
        var extMap = {
            'image/jpeg': '.jpg', 'image/png': '.png', 'image/gif': '.gif',
            'image/webp': '.webp', 'image/heic': '.heic', 'image/bmp': '.bmp',
            'image/tiff': '.tiff', 'video/mp4': '.mp4', 'video/quicktime': '.mov',
            'video/x-msvideo': '.avi', 'video/x-ms-wmv': '.wmv',
            'application/pdf': '.pdf'
        };

        function processNext() {
            if (queue.length === 0) {
                var msg = failed > 0
                    ? 'Files saved to your chosen folder (' + failed + ' failed – check console)'
                    : 'Files saved to your chosen folder.';
                showComplete(msg);
                return;
            }
            var url = queue.shift();
            current++;
            updateProgress();
            fetch(url)
                .then(function (response) {
                    var filename = '';
                    var cd = response.headers.get('Content-Disposition');
                    if (cd) {
                        var m = cd.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
                        if (m && m[1]) { filename = m[1].replace(/['"]/g, '').trim(); }
                    }
                    if (!filename) {
                        var ct = (response.headers.get('Content-Type') || '').split(';')[0].trim();
                        var idMatch = url.match(/download_photo=(\d+)/);
                        filename = 'photo' + (idMatch ? idMatch[1] : current) + (extMap[ct] || '');
                    }
                    return response.blob().then(function (blob) {
                        return { blob: blob, filename: filename };
                    });
                })
                .then(function (data) {
                    return directoryHandle.getFileHandle(data.filename, { create: true })
                        .then(function (fileHandle) { return fileHandle.createWritable(); })
                        .then(function (writable) {
                            return writable.write(data.blob).then(function () { return writable.close(); });
                        });
                })
                .then(processNext)
                .catch(function (err) {
                    failed++;
                    console.error('Failed to save file:', url, err);
                    processNext();
                });
        }
        processNext();
    } else {
        var DELAY = 900;
        function triggerNext() {
            if (queue.length === 0) {
                showComplete('Check your browser downloads folder');
                return;
            }
            var url = queue.shift();
            current++;
            updateProgress();
            var iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            document.body.appendChild(iframe);
            iframe.src = url;
            setTimeout(function () {
                if (iframe.parentNode) { iframe.parentNode.removeChild(iframe); }
            }, 60000);
            setTimeout(triggerNext, DELAY);
        }
        setTimeout(triggerNext, 150);
    }
    return false;
}

function toggleSelectMode() {
    _selectMode = !_selectMode;
    document.body.classList.toggle('select-mode', _selectMode);
    var btn = document.getElementById('selectModeBtn');
    if (btn) {
        btn.classList.toggle('active', _selectMode);
        if (_selectMode) {
            btn.innerHTML = '<i class="fas fa-times me-1"></i> Exit Selection Mode';
            btn.setAttribute('aria-label', 'Exit photo selection mode');
        } else {
            btn.innerHTML = '<i class="fas fa-check-square me-1"></i> Select Photos to Download';
            btn.setAttribute('aria-label', 'Select photos to download individually');
        }
    }
    if (!_selectMode) {
        deselectAllPhotos();
    }
}

function togglePhotoSelection(card) {
    if (!card || !card.dataset.downloadUrl) return;
    var checked = !card.classList.contains('selected');
    card.classList.toggle('selected', checked);
    var cb = card.querySelector('.photo-checkbox');
    if (cb) cb.checked = checked;
    updateSelectionBar();
}

function handleCardClick(card, event) {
    if (!_selectMode) return;
    if (!card.dataset.downloadUrl) return;
    togglePhotoSelection(card);
}

function handleMediaClick(event, callback) {
    if (_selectMode) {
        return;
    }
    callback();
}

function selectAllPhotos() {
    document.querySelectorAll('.photo-card[data-download-url]').forEach(function(card) {
        card.classList.add('selected');
        var cb = card.querySelector('.photo-checkbox');
        if (cb) cb.checked = true;
    });
    updateSelectionBar();
}

function deselectAllPhotos() {
    document.querySelectorAll('.photo-card.selected').forEach(function(card) {
        card.classList.remove('selected');
        var cb = card.querySelector('.photo-checkbox');
        if (cb) cb.checked = false;
    });
    updateSelectionBar();
}

function updateSelectionBar() {
    var selected = document.querySelectorAll('.photo-card.selected');
    var bar = document.getElementById('selectionBar');
    var cnt = document.getElementById('selCount');
    if (!bar) return;
    if (selected.length > 0) {
        bar.classList.add('sel-active');
        if (cnt) cnt.textContent = selected.length;
    } else {
        bar.classList.remove('sel-active');
    }
}

function downloadSelected() {
    downloadNowSelected();
}

function getSelectedFiles() {
    var selected = document.querySelectorAll('.photo-card.selected[data-download-url]');
    if (selected.length === 0) return _dlFiles.slice();
    var selectedUrls = new Set();
    selected.forEach(function(card) { selectedUrls.add(card.dataset.downloadUrl); });
    return _dlFiles.filter(function(f) { return selectedUrls.has(f.url); });
}

function showDownloadConfirm(filename, message, onConfirm) {
    var modal  = document.getElementById('dlConfirmModal');
    var msgEl  = document.getElementById('dlConfirmMessage');
    var nameEl = document.getElementById('dlConfirmFilename');
    var yesBtn = document.getElementById('dlConfirmYes');
    var noBtn  = document.getElementById('dlConfirmCancel');

    msgEl.textContent  = message  || 'Do you want to download this file?';
    nameEl.textContent = filename || '';

    function closeModal() { modal.style.display = 'none'; }

    yesBtn.addEventListener('click', function() {
        closeModal();
        if (typeof onConfirm === 'function') { onConfirm(); }
    }, { once: true });
    noBtn.addEventListener('click', closeModal, { once: true });
    modal.addEventListener('click', function(e) {
        if (e.target === modal) { closeModal(); }
    }, { once: true });

    modal.style.display = 'flex';
}

function confirmAndDownloadNow(files) {
    if (!files || files.length === 0) return false;
    var count   = files.length;
    var label   = count === 1 ? (files[0].filename || '1 file') : count + ' files';
    var message = count === 1
        ? 'Do you want to download this file?'
        : 'Do you want to download all ' + count + ' files?';
    showDownloadConfirm(label, message, function() { downloadNow(files); });
    return false;
}

function downloadNowSelected() {
    var files = getSelectedFiles();
    if (files.length === 0) return false;
    var count = files.length;
    var label = count === 1
        ? (files[0].filename || '1 file')
        : count + ' files';
    var message = count === 1
        ? 'Do you want to download this file?'
        : 'Do you want to download ' + count + ' selected files?';
    showDownloadConfirm(label, message, function() {
        downloadNow(files);
    });
    return false;
}

async function downloadNow(files) {
    if (!files || files.length === 0) return false;

    if (files.length > 1) {
        var ids = [];
        files.forEach(function(f) {
            var m = f.url.match(/download_photo=(\d+)/);
            if (m) { ids.push(m[1]); }
        });

        if (ids.length > 0) {
            var zipUrl = '?token=' + encodeURIComponent(window._folderToken || '')
                       + '&download_all=1&ids=' + ids.join(',');

            var overlay = document.getElementById('downloadProgressOverlay');
            var dlBar   = document.getElementById('dlBar');
            var dlPct   = document.getElementById('dlPercent');
            var dlTitle = document.getElementById('dlTitle');
            var dlFile  = document.getElementById('dlFilename');
            var dlSize  = document.getElementById('dlSizeInfo');
            var dlIcon  = document.getElementById('dlIcon');
            var dlEta   = document.getElementById('dlEta');
            var dlSpd   = document.getElementById('dlSpeed');

            if (overlay) {
                dlBar.style.width          = '100%';
                dlBar.style.background     = 'linear-gradient(90deg,#4CAF50 25%,#8BC34A 50%,#4CAF50 75%)';
                dlBar.style.backgroundSize = '200% 100%';
                dlBar.style.animation      = 'dlIndeterminate 1.5s linear infinite';
                dlPct.textContent  = '';
                dlTitle.textContent = 'Preparing ZIP download\u2026';
                dlFile.textContent = ids.length + ' files';
                dlSize.textContent = '';
                dlEta.textContent  = 'Please wait\u2026';
                dlSpd.textContent  = '';
                dlIcon.className   = 'fas fa-spinner fa-spin';
                overlay.classList.add('dl-active');
                setTimeout(function() { overlay.classList.remove('dl-active'); }, 4000);
            }
            var a = document.createElement('a');
            a.href = zipUrl;
            a.style.display = 'none';
            document.body.appendChild(a);
            a.click();
            setTimeout(function() {
                if (a.parentNode) { a.parentNode.removeChild(a); }
            }, 1000);
            return false;
        }

        var _fallbackUrls = files.map(function(f) { return f.url; });
        return bulkDownloadIndividual(_fallbackUrls, null);
    }

    if (files.length === 1) {
        startDownload(files[0].url, files[0].filename);
        return false;
    }
}

async function fetchDownloadFiles(files, lsKey, doneSet) {
    var overlay  = document.getElementById('downloadProgressOverlay');
    var dlBar    = document.getElementById('dlBar');
    var dlPct    = document.getElementById('dlPercent');
    var dlTitle  = document.getElementById('dlTitle');
    var dlFile   = document.getElementById('dlFilename');
    var dlSize   = document.getElementById('dlSizeInfo');
    var dlIcon   = document.getElementById('dlIcon');
    var dlEta    = document.getElementById('dlEta');
    var dlSpd    = document.getElementById('dlSpeed');

    var total        = files.length;
    var completed    = 0;
    var totalBytes   = 0;
    var knownBytes   = 0;
    var avgFileBytes = 0;
    var startTime    = Date.now();

    dlBar.style.width           = '0%';
    dlBar.style.background      = 'linear-gradient(90deg,#4CAF50,#8BC34A)';
    dlBar.style.backgroundSize  = '';
    dlBar.style.animation       = '';
    dlPct.textContent      = '0%';
    dlTitle.textContent    = 'Downloading your files\u2026';
    dlFile.textContent     = '';
    dlEta.textContent      = 'Calculating\u2026';
    dlSpd.textContent      = '';
    dlSize.textContent     = '0 / ' + total + ' files';
    dlIcon.className       = 'fas fa-spinner fa-spin';
    overlay.classList.add('dl-active');

    for (var i = 0; i < files.length; i++) {
        var file = files[i];
        dlFile.textContent = file.filename;

        try {
            var response = await fetch(file.url);
            if (!response.ok) throw new Error('HTTP ' + response.status);

            var contentLength = parseInt(response.headers.get('Content-Length') || '0', 10);
            var contentType   = (response.headers.get('Content-Type') || 'application/octet-stream').split(';')[0].trim();

            if (contentType === 'text/html') {
                throw new Error('Server returned an error page instead of the file. Please refresh the page and try again.');
            }

            var resolvedFilename = file.filename;
            var _cdHdr = response.headers.get('Content-Disposition');
            if (_cdHdr) {
                var _cdMatch = _cdHdr.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
                if (_cdMatch && _cdMatch[1]) {
                    var _sf = _cdMatch[1].replace(/['"]/g, '').trim();
                    if (_sf) { resolvedFilename = _sf; }
                }
            }
            if (contentLength > 0) { knownBytes += contentLength; }
            var reader        = response.body.getReader();
            var chunks        = [];
            var fileBytes     = 0;

            while (true) {
                var chunk = await reader.read();
                if (chunk.done) break;
                chunks.push(chunk.value);
                fileBytes  += chunk.value.length;
                totalBytes += chunk.value.length;

                var filePct    = contentLength > 0 ? fileBytes / contentLength : 0.5;
                var overallPct = (completed + filePct) / total;
                dlBar.style.width  = Math.round(overallPct * 100) + '%';
                dlPct.textContent  = Math.round(overallPct * 100) + '%';
                dlSize.textContent = (completed + 1) + ' / ' + total + ' files';

                var elapsed = (Date.now() - startTime) / 1000;
                if (elapsed > 0.5 && totalBytes > 0) {
                    var bps = totalBytes / elapsed;
                    dlSpd.textContent = _formatBytes(bps) + '/s';
                    if (completed > 0 || contentLength > 0) {
                        avgFileBytes = totalBytes / Math.max(completed + filePct, 0.1);
                        var estimatedRemainingBytes = avgFileBytes * (total - completed - filePct);
                        if (estimatedRemainingBytes > 0 && bps > 0) {
                            dlEta.textContent = '~' + _formatEta(estimatedRemainingBytes / bps);
                        }
                    }
                }
            }

            var blob      = new Blob(chunks, { type: contentType });
            var objectUrl = URL.createObjectURL(blob);
            var anchor    = document.createElement('a');
            anchor.href     = objectUrl;
            anchor.download = resolvedFilename;
            anchor.style.display = 'none';
            document.body.appendChild(anchor);
            anchor.click();
            document.body.removeChild(anchor);
            setTimeout(function(u) { URL.revokeObjectURL(u); }, BLOB_REVOKE_DELAY, objectUrl);

            completed++;
            if (completed > 0) { avgFileBytes = totalBytes / completed; }

            if (lsKey) {
                try {
                    if (doneSet) { doneSet.add(file.filename); }
                    var arr = doneSet ? Array.from(doneSet) : [];
                    localStorage.setItem(lsKey, JSON.stringify(arr));
                } catch (e) {}
            }

            await new Promise(function(resolve) { setTimeout(resolve, 300); });
        } catch (e) {
            console.error('Download error for ' + file.filename, e);
        }

        var pct = Math.round((completed / total) * 100);
        dlBar.style.width  = pct + '%';
        dlPct.textContent  = pct + '%';
        dlSize.textContent = completed + ' / ' + total + ' files';
        var elapsed2 = (Date.now() - startTime) / 1000;
        if (elapsed2 > 0.5 && completed > 0) {
            var bps2 = totalBytes / elapsed2;
            dlSpd.textContent = _formatBytes(bps2) + '/s';
            var rem2 = total - completed;
            if (rem2 > 0 && bps2 > 0) {
                var etaBytes2 = avgFileBytes * rem2;
                dlEta.textContent = '~' + _formatEta(etaBytes2 / bps2);
            } else {
                dlEta.textContent = '';
            }
        }
    }

    _showDlCompleteMessage('Download complete!', completed);
}

function _showDlCompleteMessage(title, count) {
    var overlay = document.getElementById('downloadProgressOverlay');
    var dlBar   = document.getElementById('dlBar');
    var dlPct   = document.getElementById('dlPercent');
    var dlTitle = document.getElementById('dlTitle');
    var dlFile  = document.getElementById('dlFilename');
    var dlSize  = document.getElementById('dlSizeInfo');
    var dlIcon  = document.getElementById('dlIcon');
    var dlEta   = document.getElementById('dlEta');
    var dlSpd   = document.getElementById('dlSpeed');

    dlBar.style.width  = '100%';
    dlPct.textContent  = '100%';
    dlTitle.textContent = title;
    dlFile.textContent  = '';
    dlSize.textContent  = 'All ' + count + ' files downloaded';
    dlEta.textContent   = '';
    dlSpd.textContent   = '';
    dlIcon.className    = 'fas fa-check-circle';
    overlay.classList.add('dl-active');
    setTimeout(function() { overlay.classList.remove('dl-active'); }, 3000);
}

function showResumeDialog(alreadyCount, remainingCount) {
    return new Promise(function(resolve) {
        var modal = document.getElementById('resumeModal');
        document.getElementById('resumeAlreadyCount').textContent   = alreadyCount;
        document.getElementById('resumeRemainingCount').textContent = remainingCount;

        var btnRemaining  = document.getElementById('resumeBtnRemaining');
        var descPartial   = document.getElementById('resumeDescPartial');
        var descAll       = document.getElementById('resumeDescAll');
        var allCountSpan  = document.getElementById('resumeAllCount');

        if (remainingCount === 0) {
            btnRemaining.style.display = 'none';
            if (allCountSpan) allCountSpan.textContent = alreadyCount;
            if (descPartial) descPartial.style.display = 'none';
            if (descAll)     descAll.style.display     = '';
        } else {
            btnRemaining.style.display = '';
            if (descPartial) descPartial.style.display = '';
            if (descAll)     descAll.style.display     = 'none';
        }

        function cleanup(value) {
            modal.style.display              = 'none';
            btnRemaining.onclick             = null;
            btnAll.onclick                   = null;
            btnCancel.onclick                = null;
            resolve(value);
        }

        var btnAll       = document.getElementById('resumeBtnAll');
        var btnCancel    = document.getElementById('resumeBtnCancel');
        btnRemaining.onclick = function() { cleanup('remaining'); };
        btnAll.onclick       = function() { cleanup('all'); };
        btnCancel.onclick    = function() { cleanup(null); };

        modal.style.display = 'flex';
    });
}

function _formatEta(seconds) {
    if (!isFinite(seconds) || seconds <= 0) return '';
    if (seconds < 60)   return Math.ceil(seconds) + 's';
    if (seconds < 3600) return Math.ceil(seconds / 60) + 'min';
    return Math.ceil(seconds / 3600) + 'hr';
}

function _formatBytes(bytes) {
    var KB = 1024, MB = 1024 * 1024;
    if (bytes < KB) return bytes.toFixed(0) + ' B';
    if (bytes < MB) return (bytes / KB).toFixed(1) + ' KB';
    return (bytes / MB).toFixed(1) + ' MB';
}

function setViewMode(mode) {
    document.body.classList.remove('list-mode', 'grid-mode');
    if (mode === 'list') {
        document.body.classList.add('list-mode');
    }
    try { localStorage.setItem('folderViewMode', mode); } catch(e) {}
    var gridBtn = document.getElementById('viewGridBtn');
    var listBtn = document.getElementById('viewListBtn');
    if (gridBtn) gridBtn.classList.toggle('active', mode !== 'list');
    if (listBtn) listBtn.classList.toggle('active', mode === 'list');
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        var savedMode = localStorage.getItem('folderViewMode');
        if (savedMode === 'list') { setViewMode('list'); }
    } catch(e) {}
});
