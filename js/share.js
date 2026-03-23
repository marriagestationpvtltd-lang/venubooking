// Floating page share button
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
        waLink.addEventListener('click', function(event) {
            waLink.href = 'https://wa.me/?text=' + encodeURIComponent(shareUrl);
            event.stopPropagation();
            closeShare();
        });
    }

    if (fbLink) {
        fbLink.addEventListener('click', function(event) {
            fbLink.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl);
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
