// Global section share buttons (inline, attached to page sections)
(function initSectionShare() {
    var toast = null;
    var toastTimer = null;

    function getToast() {
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'share-copied-toast';
            document.body.appendChild(toast);
        }
        return toast;
    }

    function showToast(msg) {
        var t = getToast();
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { t.classList.remove('show'); }, 2500);
    }

    function getShareUrl(sectionId) {
        if (sectionId) {
            var safeId = (window.CSS && CSS.escape) ? CSS.escape(sectionId) : sectionId.replace(/[[\]"\\]/g, '\\$&');
            var wrap = document.querySelector('[data-share-wrap="' + safeId + '"]');
            if (wrap) {
                var pageUrl = wrap.getAttribute('data-page-url');
                if (pageUrl) return pageUrl;
                return window.location.origin + window.location.pathname + '#' + sectionId;
            }
        }
        return window.location.href;
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.section-share-dropdown.open').forEach(function (d) {
            d.classList.remove('open');
        });
        document.querySelectorAll('.section-share-btn.active').forEach(function (b) {
            b.classList.remove('active');
            b.setAttribute('aria-expanded', 'false');
        });
    }

    function fallbackCopy(text) {
        var el = document.createElement('textarea');
        el.value = text;
        el.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
        el.setAttribute('aria-hidden', 'true');
        document.body.appendChild(el);
        el.select();
        try {
            document.execCommand('copy');
            showToast('✓ Link copied!');
        } catch (err) {
            showToast('Unable to copy link');
        }
        document.body.removeChild(el);
    }

    document.addEventListener('click', function (e) {
        // Toggle dropdown on share button click
        var shareBtn = e.target.closest('.section-share-btn');
        if (shareBtn) {
            e.stopPropagation();
            var wrap = shareBtn.closest('.section-share-wrap');
            var dropdown = wrap ? wrap.querySelector('.section-share-dropdown') : null;
            if (!dropdown) return;
            var isOpen = dropdown.classList.contains('open');
            closeAllDropdowns();
            if (!isOpen) {
                dropdown.classList.add('open');
                shareBtn.classList.add('active');
                shareBtn.setAttribute('aria-expanded', 'true');
            }
            return;
        }

        // Copy link
        var copyBtn = e.target.closest('.share-copy');
        if (copyBtn) {
            e.preventDefault();
            e.stopPropagation();
            var url = getShareUrl(copyBtn.getAttribute('data-section'));
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url)
                    .then(function () { showToast('✓ Link copied!'); })
                    .catch(function () { fallbackCopy(url); });
            } else {
                fallbackCopy(url);
            }
            closeAllDropdowns();
            return;
        }

        // WhatsApp share
        var waBtn = e.target.closest('.share-whatsapp');
        if (waBtn) {
            e.preventDefault();
            e.stopPropagation();
            var url = getShareUrl(waBtn.getAttribute('data-section'));
            waBtn.href = 'https://wa.me/?text=' + encodeURIComponent(url);
            window.open(waBtn.href, '_blank', 'noopener');
            closeAllDropdowns();
            return;
        }

        // Facebook share
        var fbBtn = e.target.closest('.share-facebook');
        if (fbBtn) {
            e.preventDefault();
            e.stopPropagation();
            var url = getShareUrl(fbBtn.getAttribute('data-section'));
            fbBtn.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
            window.open(fbBtn.href, '_blank', 'noopener');
            closeAllDropdowns();
            return;
        }

        // Close on outside click
        if (!e.target.closest('.section-share-wrap')) {
            closeAllDropdowns();
        }
    });
}());

// Floating page share button (used on standalone pages like folder.php, download.php)
(function initPageShare() {
    var wrap = document.querySelector('.page-share-wrap');
    if (!wrap) return;

    var button = wrap.querySelector('.page-share-btn');
    var dropdown = wrap.querySelector('.page-share-dropdown');
    var copyBtn = wrap.querySelector('.page-share-copy');
    var waLink = wrap.querySelector('.page-share-whatsapp');
    var fbLink = wrap.querySelector('.page-share-facebook');
    var shareUrl = window.location.href;
    var toastTimer = null;

    function showToast(message) {
        var toast = document.querySelector('.page-share-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'page-share-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('show');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function() { toast.classList.remove('show'); }, 2400);
    }

    function closeShare() {
        if (!dropdown || !button) return;
        dropdown.classList.remove('open');
        button.classList.remove('active');
        button.setAttribute('aria-expanded', 'false');
    }

    function openShare() {
        if (!dropdown || !button) return;
        dropdown.classList.add('open');
        button.classList.add('active');
        button.setAttribute('aria-expanded', 'true');
    }

    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.cssText = 'position:fixed;top:-1000px;left:-1000px;opacity:0;';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            showToast('Link copied!');
        } catch (err) {
            showToast('Unable to copy link');
        }
        document.body.removeChild(textarea);
    }

    if (button) {
        button.addEventListener('click', function(event) {
            event.stopPropagation();
            if (dropdown && dropdown.classList.contains('open')) {
                closeShare();
            } else {
                openShare();
            }
        });
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shareUrl)
                    .then(function() { showToast('Link copied!'); })
                    .catch(function() { fallbackCopy(shareUrl); });
            } else {
                fallbackCopy(shareUrl);
            }
            closeShare();
        });
    }

    if (waLink) {
        waLink.href = 'https://wa.me/?text=' + encodeURIComponent(shareUrl);
        waLink.addEventListener('click', function(event) {
            event.stopPropagation();
            closeShare();
        });
    }

    if (fbLink) {
        fbLink.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl);
        fbLink.addEventListener('click', function(event) {
            event.stopPropagation();
            closeShare();
        });
    }

    document.addEventListener('click', function(event) {
        if (!wrap.contains(event.target)) {
            closeShare();
        }
    });
}());
