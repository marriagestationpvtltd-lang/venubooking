/**
 * nepali-cultural.js
 * Brings Nepali culture to life on the landing page:
 *   – Prayer flags (लुङ्दर) waving across the hero
 *   – Falling marigold petals (गेंदाको पंखुडी)
 *   – Diya sparkle particles (दियाको चिनगारी)
 *   – Cultural greeting text cycle (सांस्कृतिक स्वागत)
 */
(function () {
    'use strict';

    /* ── Utility ──────────────────────────────────────────────── */
    var reducedMotion = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
        : false;

    /* ════════════════════════════════════════════════════════════
       1. PRAYER FLAGS (लुङ्दर)
       Five sacred colors (blue, white, red, green, yellow) on a rope
    ════════════════════════════════════════════════════════════ */
    var FLAG_COLORS = ['#2980B9', '#FFFFFF', '#C0392B', '#27AE60', '#F1C40F'];

    function buildPrayerFlags() {
        var inner = document.querySelector('.prayer-flags-inner');
        if (!inner) return;

        var flagW   = window.innerWidth <= 767 ? 20 : 24;
        var flagGap = 4;
        var step    = flagW + flagGap;
        var count   = Math.ceil((window.innerWidth + 60) / step) + 2;

        var html = '<div class="prayer-flag-rope"></div>';
        for (var i = 0; i < count; i++) {
            var color = FLAG_COLORS[i % FLAG_COLORS.length];
            var left  = i * step;
            html += '<div class="prayer-flag" style="left:' + left + 'px;background:' + color + ';"></div>';
        }
        inner.innerHTML = html;
    }

    /* ════════════════════════════════════════════════════════════
       2. FALLING MARIGOLD PETALS (गेंदाको पंखुडी)
       Warm saffron & orange petals drifting down the hero section
    ════════════════════════════════════════════════════════════ */
    var PETAL_COLORS = [
        '#FF8C00', '#FFA500', '#FFB347', '#FF6B35',
        '#FFD700', '#FF7518', '#E25822', '#FFC300'
    ];

    function spawnPetal(container) {
        var el = document.createElement('div');
        el.className = 'nepal-petal';

        var size     = 7 + Math.random() * 11;          // 7–18 px
        var color    = PETAL_COLORS[Math.floor(Math.random() * PETAL_COLORS.length)];
        var leftPct  = Math.random() * 100;
        var dur      = 7 + Math.random() * 9;            // 7–16 s
        var delay    = -(Math.random() * dur);            // pre-seeded so they start mid-flight
        var rot      = Math.floor(Math.random() * 360);
        var swayMid  = (Math.random() > 0.5 ? 1 : -1) * (8 + Math.random() * 22);
        var swayEnd  = -swayMid * (0.5 + Math.random() * 0.8);
        var rx1      = 50 + Math.random() * 30;
        var rx2      = Math.random() * 25;
        var rx3      = 50 + Math.random() * 30;

        el.style.cssText = [
            'width:'                  + size + 'px',
            'height:'                 + (size * 0.62) + 'px',
            'background:'             + color,
            'left:'                   + leftPct + '%',
            'border-radius:'          + rx1 + '% ' + rx2 + '% ' + rx3 + '% 0',
            'animation-duration:'     + dur + 's',
            'animation-delay:'        + delay + 's',
            'transform:rotate('       + rot + 'deg)',
            '--petal-sway-mid:'       + swayMid + 'px',
            '--petal-sway-end:'       + swayEnd + 'px',
            'filter:drop-shadow(0 1px 2px rgba(0,0,0,0.18))'
        ].join(';');

        container.appendChild(el);
    }

    function initPetals() {
        if (reducedMotion) return;
        var container = document.querySelector('.nepal-petals-container');
        if (!container) return;

        // Scale count with viewport width, but cap it to stay performant
        var count = Math.min(28, Math.max(8, Math.floor(window.innerWidth / 55)));
        for (var i = 0; i < count; i++) {
            spawnPetal(container);
        }
    }

    /* ════════════════════════════════════════════════════════════
       3. DIYA SPARKLE PARTICLES (दियाको चिनगारी)
       Tiny golden sparks rising from the foot of the hero
    ════════════════════════════════════════════════════════════ */
    function initDiyaParticles() {
        if (reducedMotion) return;
        var container = document.querySelector('.nepal-diya-particles');
        if (!container) return;

        var count = 14;
        for (var i = 0; i < count; i++) {
            var spark   = document.createElement('div');
            spark.className = 'diya-spark';

            var size    = 2.5 + Math.random() * 4;
            var leftPct = 4 + Math.random() * 92;
            var dur     = 2.2 + Math.random() * 3.2;
            var delay   = -(Math.random() * dur);
            var drift   = (Math.random() - 0.5) * 36;
            var bottom  = Math.random() * 16;

            spark.style.cssText = [
                'width:'              + size + 'px',
                'height:'             + size + 'px',
                'left:'               + leftPct + '%',
                'bottom:'             + bottom + 'px',
                '--drift:'            + drift + 'px',
                'animation-duration:' + dur + 's',
                'animation-delay:'    + delay + 's'
            ].join(';');

            container.appendChild(spark);
        }
    }

    /* ════════════════════════════════════════════════════════════
       4. CULTURAL GREETING TEXT CYCLE (सांस्कृतिक स्वागत)
       Cycles through Nepali greetings in the welcome strip
    ════════════════════════════════════════════════════════════ */
    var GREETINGS = [
        '🙏 नमस्ते — तपाईंको शुभ समारोहको लागि सर्वोत्तम भेन्यू बुकिंग गर्नुहोस्',
        '🎊 स्वागत छ — नेपाली संस्कृतिले सजिएको सपनाको बिहे र उत्सव',
        '🌸 शुभकामना — हाम्रो प्रिमियम भेन्यूहरूले तपाईंको समारोह अविस्मरणीय बनाउनेछ',
        '🪔 दीपावली, बिहे, ब्रतबन्ध — हरेक उत्सवको लागि परिपूर्ण भेन्यू'
    ];

    function initGreetingCycle() {
        var el = document.querySelector('.nc-strip-item');
        if (!el) return;

        var idx = 0;
        el.textContent = GREETINGS[0];

        setInterval(function () {
            el.style.opacity   = '0';
            el.style.transform = 'translateY(-10px)';

            setTimeout(function () {
                idx = (idx + 1) % GREETINGS.length;
                el.textContent = GREETINGS[idx];
                el.style.opacity   = '1';
                el.style.transform = 'translateY(0)';
            }, 480);
        }, 5500);
    }

    /* ════════════════════════════════════════════════════════════
       Bootstrap
    ════════════════════════════════════════════════════════════ */
    function init() {
        buildPrayerFlags();
        initPetals();
        initDiyaParticles();
        initGreetingCycle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
