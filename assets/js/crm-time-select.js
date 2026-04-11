(function () {
    'use strict';

    function normalizeTime(value) {
        var raw = String(value || '').trim();
        if (!raw) return '';

        var m = raw.match(/^(\d{1,2}):(\d{2})/);
        if (!m) return '';

        var hh = Math.max(0, Math.min(23, parseInt(m[1], 10)));
        var mm = Math.max(0, Math.min(59, parseInt(m[2], 10)));
        return String(hh).padStart(2, '0') + ':' + String(mm).padStart(2, '0');
    }

    function parseStepMinutes(input) {
        var raw = String(input.getAttribute('step') || '').trim();
        if (!raw) return 5;
        var sec = parseInt(raw, 10);
        if (!isFinite(sec) || sec <= 0) return 5;
        var min = Math.round(sec / 60);
        if (!isFinite(min) || min <= 0) min = 1;
        return Math.max(1, Math.min(60, min));
    }

    function formatLabel(hh, mm) {
        var period = hh >= 12 ? 'PM' : 'AM';
        var h12 = hh % 12;
        if (h12 === 0) h12 = 12;
        return String(h12).padStart(2, '0') + ':' + String(mm).padStart(2, '0') + ' ' + period;
    }

    function buildSelect(stepMinutes, selected) {
        var select = document.createElement('select');
        select.className = 'crm-time-select-native';

        var blank = document.createElement('option');
        blank.value = '';
        blank.textContent = '--:--';
        select.appendChild(blank);

        for (var h = 0; h < 24; h++) {
            for (var m = 0; m < 60; m += stepMinutes) {
                var value = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                var opt = document.createElement('option');
                opt.value = value;
                opt.textContent = formatLabel(h, m);
                if (value === selected) opt.selected = true;
                select.appendChild(opt);
            }
        }

        return select;
    }

    function applyStylesOnce() {
        if (document.getElementById('crm-time-select-style')) return;

        var style = document.createElement('style');
        style.id = 'crm-time-select-style';
        style.textContent = [
            '.crm-time-select-wrap{position:relative;display:block;width:100%;}',
            '.crm-time-select-wrap .crm-time-source{position:absolute !important;inset:0 !important;opacity:0 !important;pointer-events:none !important;}',
            '.crm-time-select-native{width:100%;min-height:42px;padding:10px 36px 10px 12px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;color:#1f2937;font-weight:600;}',
            '.crm-time-select-native:focus{outline:none;border-color:rgba(233,30,99,.55);box-shadow:0 0 0 4px rgba(233,30,99,.12);}',
            '.crm-time-select-wrap::after{content:"\\f107";font-family:"Font Awesome 6 Free";font-weight:900;position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;}'
        ].join('');
        document.head.appendChild(style);
    }

    function transformOne(input) {
        if (!input || input.dataset.timeSelectReady === '1') return;
        if (input.getAttribute('data-native-time') === 'on') return;
        if (input.closest('[data-keep-native-time="on"]')) return;
        if (input.type !== 'time') return;

        var selected = normalizeTime(input.value);
        var stepMinutes = parseStepMinutes(input);

        var wrap = document.createElement('div');
        wrap.className = 'crm-time-select-wrap';

        var select = buildSelect(stepMinutes, selected);
        if (input.disabled) select.disabled = true;
        if (input.required) select.required = true;

        input.classList.add('crm-time-source');
        input.dataset.timeSelectReady = '1';

        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        wrap.appendChild(select);

        select.addEventListener('change', function () {
            input.value = normalizeTime(select.value);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        input.addEventListener('change', function () {
            var v = normalizeTime(input.value);
            if (select.value !== v) select.value = v;
        });
    }

    function scan(root) {
        var scope = root || document;
        var inputs = scope.querySelectorAll ? scope.querySelectorAll('input[type="time"]') : [];
        inputs.forEach(transformOne);
    }

    function init() {
        applyStylesOnce();
        scan(document);

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (!node || node.nodeType !== 1) return;
                    if (node.matches && node.matches('input[type="time"]')) transformOne(node);
                    scan(node);
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
