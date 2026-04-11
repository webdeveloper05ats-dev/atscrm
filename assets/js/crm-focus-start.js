(function () {
    'use strict';

    function isVisible(el) {
        if (!el) return false;
        if (el.offsetParent !== null) return true;
        return getComputedStyle(el).position === 'fixed';
    }

    function isFocusableInput(el) {
        if (!el) return false;
        if (!el.matches('input, select, textarea')) return false;
        if (el.disabled || el.readOnly) return false;
        var type = String(el.type || '').toLowerCase();
        if (type === 'hidden' || type === 'button' || type === 'submit' || type === 'file' || type === 'checkbox' || type === 'radio') {
            return false;
        }
        return isVisible(el);
    }

    function findTarget(form) {
        var selector = String(form.getAttribute('data-focus-target') || '').trim();
        var target = null;

        if (selector) {
            target = form.querySelector(selector) || document.querySelector(selector);
            if (isFocusableInput(target)) return target;
        }

        var fields = Array.prototype.slice.call(form.querySelectorAll('input, select, textarea')).filter(isFocusableInput);
        return fields.length ? fields[0] : null;
    }

    function blink(el) {
        if (!el) return;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        el.classList.add('crm-focus-blink');
        setTimeout(function () { el.classList.remove('crm-focus-blink'); }, 1800);
    }

    function applyForm(form) {
        if (!form || form.dataset.focusStartApplied === '1') return;
        if (String(form.getAttribute('data-focus-start') || '').toLowerCase() !== 'on') return;
        if (!isVisible(form)) return;

        // Don't steal focus if user already focused elsewhere.
        if (document.activeElement && document.activeElement !== document.body && document.activeElement !== document.documentElement) {
            return;
        }

        var target = findTarget(form);
        if (!target) return;

        form.dataset.focusStartApplied = '1';
        setTimeout(function () {
            target.focus();
            if (target.tagName === 'INPUT' && typeof target.select === 'function') {
                target.select();
            }
            blink(target);
        }, 120);
    }

    function applyAll(root) {
        var scope = root || document;
        var forms = scope.matches && scope.matches('form[data-focus-start="on"]')
            ? [scope]
            : Array.prototype.slice.call(scope.querySelectorAll('form[data-focus-start="on"]'));
        forms.forEach(applyForm);
    }

    function injectStyle() {
        if (document.getElementById('crm-focus-start-style')) return;
        var style = document.createElement('style');
        style.id = 'crm-focus-start-style';
        style.textContent = [
            '@keyframes crmFocusPulse {',
            '  0% { box-shadow: 0 0 0 0 rgba(233,30,99,.45); }',
            '  70% { box-shadow: 0 0 0 10px rgba(233,30,99,0); }',
            '  100% { box-shadow: 0 0 0 0 rgba(233,30,99,0); }',
            '}',
            '.crm-focus-blink {',
            '  border-color: rgba(233,30,99,.75) !important;',
            '  animation: crmFocusPulse .85s ease 2;',
            '}'
        ].join('');
        document.head.appendChild(style);
    }

    function init() {
        injectStyle();
        applyAll(document);

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (!node || node.nodeType !== 1) return;
                    applyAll(node);
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
