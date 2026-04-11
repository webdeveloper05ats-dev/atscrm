(function () {
    'use strict';

    // Very safe mode:
    // The helper runs only on forms explicitly marked with data-form-assist="on".
    var STRICT_OPT_IN_MODE = true;

    var EXCLUDED_FORM_IDS = {
        dailyReportForm: true,
        hrDailyForm: true,
        mkDailyForm: true
    };

    function isElementVisible(el) {
        if (!el) return false;
        if (el.offsetParent !== null) return true;
        return getComputedStyle(el).position === 'fixed';
    }

    function isSupportedField(el) {
        if (!el) return false;
        if (!el.matches('input, select, textarea')) return false;
        if (el.disabled || el.readOnly) return false;

        var type = String(el.type || '').toLowerCase();
        if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'file' || type === 'checkbox' || type === 'radio') {
            return false;
        }

        return isElementVisible(el);
    }

    function canUseFormAssist(form) {
        if (!form || !form.tagName || form.tagName.toLowerCase() !== 'form') return false;
        if (EXCLUDED_FORM_IDS[form.id]) return false;
        if (form.hasAttribute('data-disable-form-assist')) return false;
        if (form.classList.contains('filter-form')) return false;

        var method = String(form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post') return false;

        if (STRICT_OPT_IN_MODE) {
            return String(form.getAttribute('data-form-assist') || '').toLowerCase() === 'on';
        }

        return true;
    }

    function getNavigableFields(form) {
        var all = Array.prototype.slice.call(form.querySelectorAll('input, select, textarea'));
        return all.filter(function (el) {
            if (!isSupportedField(el)) return false;
            if (el.matches('textarea') && !el.hasAttribute('data-enter-nav')) return false;
            return true;
        });
    }

    function handleEnterNavigation(e) {
        if (e.key !== 'Enter') return;

        var target = e.target;
        if (!target || !target.form) return;
        if (!isSupportedField(target)) return;
        if (target.matches('textarea') && !target.hasAttribute('data-enter-nav')) return;
        if (target.closest('[contenteditable="true"]')) return;

        var form = target.form;
        if (!canUseFormAssist(form)) return;

        var fields = getNavigableFields(form);
        if (fields.length < 2) return;

        var index = fields.indexOf(target);
        if (index < 0) return;

        e.preventDefault();

        if (index < fields.length - 1) {
            var next = fields[index + 1];
            next.focus();
            if (next.tagName === 'INPUT' && typeof next.select === 'function') {
                next.select();
            }
        }
    }

    function getRowFields(tr) {
        var all = Array.prototype.slice.call(tr.querySelectorAll('input, textarea, select'));
        return all.filter(isSupportedField);
    }

    function handleGridPaste(e) {
        var target = e.target;
        if (!target || !target.form) return;
        if (!target.matches('input, textarea, select')) return;

        var form = target.form;
        if (!canUseFormAssist(form)) return;

        var tr = target.closest('tr');
        if (!tr) return;

        var table = tr.closest('table');
        if (!table) return;

        // In strict mode, paste is allowed only on explicitly marked tables.
        if (STRICT_OPT_IN_MODE) {
            if (String(table.getAttribute('data-grid-paste') || '').toLowerCase() !== 'on') return;
        }

        var text = '';
        if (e.clipboardData && typeof e.clipboardData.getData === 'function') {
            text = e.clipboardData.getData('text');
        } else if (window.clipboardData && typeof window.clipboardData.getData === 'function') {
            text = window.clipboardData.getData('Text');
        }

        if (!text) return;
        if (text.indexOf('\t') === -1 && text.indexOf('\n') === -1 && text.indexOf('\r') === -1) return;

        var rowContainer = table.querySelector('tbody');
        if (!rowContainer) return;

        var rows = Array.prototype.slice.call(rowContainer.querySelectorAll('tr'));
        var startRow = rows.indexOf(tr);
        if (startRow < 0) return;

        var startFields = getRowFields(tr);
        var startCol = startFields.indexOf(target);
        if (startCol < 0) return;

        var matrix = text
            .replace(/\r/g, '')
            .split('\n')
            .filter(function (line) { return line !== ''; })
            .map(function (line) { return line.split('\t'); });

        if (!matrix.length) return;

        e.preventDefault();

        matrix.forEach(function (cols, rowOffset) {
            var row = rows[startRow + rowOffset];
            if (!row) return;

            var fields = getRowFields(row);
            cols.forEach(function (value, colOffset) {
                var cell = fields[startCol + colOffset];
                if (!cell) return;
                cell.value = value;
                cell.dispatchEvent(new Event('input', { bubbles: true }));
                cell.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    document.addEventListener('keydown', handleEnterNavigation, true);
    document.addEventListener('paste', handleGridPaste, true);
})();
