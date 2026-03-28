/**
 * design-zoom.js
 * Hover zoom preview for design/service photos in the booking flow.
 *
 * When the user hovers over a design image (additional services design grid
 * or service-package feature icons) a 400×400 px preview of the
 * full-resolution image appears next to the cursor so the design can be
 * clearly evaluated before confirming a booking.
 *
 * Targets:
 *   .design-card-img          – inline design photo grid (booking-step5 PHP)
 *   .design-card-img-mob      – mobile variant of the above
 *   .design-card .card-img-top – JS-rendered design grid (booking-step5.js)
 *   .pkg-feat-icon-img         – service-package feature icons (booking-step4)
 */
(function () {
    'use strict';

    /* ── inject CSS ─────────────────────────────────────────────────────────── */
    var css = [
        '#design-zoom-preview{',
        '  position:fixed;z-index:99999;pointer-events:none;',
        '  width:400px;height:400px;border-radius:8px;overflow:hidden;',
        '  border:3px solid #198754;',
        '  box-shadow:0 6px 28px rgba(0,0,0,.38),0 0 0 5px rgba(25,135,84,.18);',
        '  background:#f8f9fa;display:none;opacity:0;',
        '  transition:opacity .18s ease,transform .18s ease;',
        '  transform:scale(.85);',
        '}',
        '#design-zoom-preview.dzp-visible{opacity:1;transform:scale(1);}',
        '#design-zoom-preview img{width:100%;height:100%;object-fit:cover;display:block;}'
    ].join('');

    var styleEl = document.createElement('style');
    styleEl.textContent = css;
    document.head.appendChild(styleEl);

    /* ── create preview element ─────────────────────────────────────────────── */
    var preview    = document.createElement('div');
    preview.id     = 'design-zoom-preview';
    var previewImg = document.createElement('img');
    previewImg.alt  = 'Design preview';
    previewImg.setAttribute('role', 'presentation');
    preview.appendChild(previewImg);
    document.body.appendChild(preview);

    var active  = false;
    var rafPending = false;

    /* ── position helper ────────────────────────────────────────────────────── */
    function positionPreview(clientX, clientY) {
        var size   = 400;
        var offset = 18;
        var vpW    = window.innerWidth;
        var vpH    = window.innerHeight;

        var x = clientX + offset;
        var y = clientY - size / 2;

        /* keep within viewport */
        if (x + size + 8 > vpW) { x = clientX - size - offset; }
        if (y < 8)               { y = 8; }
        if (y + size + 8 > vpH)  { y = vpH - size - 8; }

        preview.style.left = x + 'px';
        preview.style.top  = y + 'px';
    }

    /* ── match helper ───────────────────────────────────────────────────────── */
    function isZoomTarget(el) {
        if (!el || el.tagName !== 'IMG') { return false; }

        /* direct class matches */
        if (el.classList.contains('design-card-img'))     { return true; }
        if (el.classList.contains('design-card-img-mob')) { return true; }
        if (el.classList.contains('pkg-feat-icon-img'))   { return true; }

        /* .design-card .card-img-top (JS-rendered grid) */
        if (el.classList.contains('card-img-top')) {
            var parent = el.parentElement;
            while (parent) {
                if (parent.classList && parent.classList.contains('design-card')) {
                    return true;
                }
                parent = parent.parentElement;
            }
        }

        return false;
    }

    /* ── event handlers ─────────────────────────────────────────────────────── */
    document.addEventListener('mouseover', function (e) {
        var el = e.target;
        if (!isZoomTarget(el) || !el.src) { return; }
        /* Skip on touch/mobile: the 400 px preview overflows narrow viewports */
        if (window.matchMedia && window.matchMedia('(hover: none)').matches) { return; }

        previewImg.src = el.src;
        preview.style.display = 'block';
        void preview.offsetWidth;                         /* force reflow → enable transition */
        preview.classList.add('dzp-visible');
        active = true;
        positionPreview(e.clientX, e.clientY);
    });

    document.addEventListener('mouseout', function (e) {
        var el = e.target;
        if (!isZoomTarget(el)) { return; }

        active = false;
        preview.classList.remove('dzp-visible');
        setTimeout(function () {
            if (!active) {
                preview.style.display = 'none';
                previewImg.src = '';
            }
        }, 200);
    });

    document.addEventListener('mousemove', function (e) {
        if (!active || rafPending) { return; }
        rafPending = true;
        requestAnimationFrame(function () {
            rafPending = false;
            if (active) { positionPreview(e.clientX, e.clientY); }
        });
    });

}());
