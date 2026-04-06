<?php
// ============================================
// ATS CRM - Footer Layout (STABLE VERSION)
// ============================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}
?>

<!-- ============================= -->
<!-- Core JS Libraries -->
<!-- ============================= -->

<!-- jQuery (FIRST) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<!-- DataTables Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>

<!-- JSZip (for Excel export) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<!-- Export Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- ============================= -->
<!-- CRM Custom Scripts -->
<!-- ============================= -->

<script src="assets/js/crm-datatable.js"></script>
<script src="assets/js/modern-select.js"></script>
<script src="assets/js/modern-datepicker.js"></script>

<script>
(function () {
    function normalizeStatusKey(text) {
        return String(text || '').trim().toLowerCase().replace(/\s+/g, '-');
    }

    function applyUniversalStatusBadges(root) {
        const scope = root || document;
        const candidates = scope.querySelectorAll('.status-badge, .status-pill, .badge-status, [data-status]');

        candidates.forEach(function (el) {
            if (!el || el.dataset.crmStatusReady === '1') return;

            const raw = el.getAttribute('data-status') || el.textContent || '';
            const key = normalizeStatusKey(raw);
            if (!key) return;

            el.classList.add('crm-status-badge', 'is-' + key);
            el.dataset.crmStatusReady = '1';
        });
    }

    function applyUniversalPageHeaders(root) {
        const scope = root || document;

        const heads = scope.querySelectorAll('.dashboard-header, .role-page-head, .enq-page-head, .page-header, .menu-page-head');
        heads.forEach(function (el) {
            if (!el || el.dataset.crmHeadReady === '1') return;
            el.classList.add('crm-page-head');
            el.dataset.crmHeadReady = '1';
        });

        const titles = scope.querySelectorAll('.page-title, .enq-page-title, .dashboard-header h2, .role-page-head h2');
        titles.forEach(function (el) {
            if (!el || el.dataset.crmTitleReady === '1') return;
            el.classList.add('crm-page-title');
            el.dataset.crmTitleReady = '1';
        });

        const metas = scope.querySelectorAll('.header-stats, .role-total-badge, .menu-total-badge');
        metas.forEach(function (el) {
            if (!el || el.dataset.crmMetaReady === '1') return;
            el.classList.add('crm-page-meta');
            el.dataset.crmMetaReady = '1';
        });
    }

    function normalizeDataTableToolbars(root) {
        const scope = root || document;
        const wrappers = scope.matches && scope.matches('.dataTables_wrapper')
            ? [scope]
            : Array.from(scope.querySelectorAll('.dataTables_wrapper'));

        wrappers.forEach(function (wrapper) {
            if (!wrapper || wrapper.dataset.crmDtToolbarReady === '1') return;

            const top = wrapper.querySelector('.crm-table-header, .dt-top');
            const bottom = wrapper.querySelector('.crm-table-footer, .dt-bottom');

            if (top) top.classList.add('crm-table-header');
            if (bottom) bottom.classList.add('crm-table-footer');

            wrapper.dataset.crmDtToolbarReady = '1';
        });
    }

    function bindObserver() {
        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (!node || node.nodeType !== 1) return;
                    normalizeDataTableToolbars(node);
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    document.addEventListener('DOMContentLoaded', function () {
        applyUniversalStatusBadges(document);
        applyUniversalPageHeaders(document);
        normalizeDataTableToolbars(document);
        bindObserver();
    });

    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (!node || node.nodeType !== 1) return;
                applyUniversalStatusBadges(node);
                applyUniversalPageHeaders(node);
            });
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        observer.observe(document.body, { childList: true, subtree: true });
    });
})();
</script>

<script>
(function () {
    function applyModernSearchUI(root) {
        const scope = root || document;
        const inputs = scope.querySelectorAll('input[name="q"], .dataTables_filter input[type="search"]');

        inputs.forEach(function (input) {
            if (!input || input.dataset.modernSearchReady === '1') return;
            if (input.closest('.sidebar')) return; // Explicitly skip sidebar search

            if (input.matches('.dataTables_filter input[type="search"]')) {
                const filterWrap = input.closest('.dataTables_filter');
                if (filterWrap) filterWrap.classList.add('crm-modern-filter');
            }

            const inExistingSearchField = input.closest('.search-field');

            if (inExistingSearchField) {
                inExistingSearchField.classList.add('crm-modern-search');
                const existingIcon = inExistingSearchField.querySelector('.search-field-icon, .crm-modern-search-icon');
                if (existingIcon) {
                    existingIcon.classList.add('crm-modern-search-icon');
                } else {
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-search crm-modern-search-icon';
                    inExistingSearchField.insertBefore(icon, input);
                }
                input.dataset.modernSearchReady = '1';
                return;
            }

            const wrap = document.createElement('div');
            wrap.className = 'crm-modern-search';

            const icon = document.createElement('i');
            icon.className = 'fas fa-search crm-modern-search-icon';

            const parent = input.parentNode;
            if (!parent) return;

            parent.insertBefore(wrap, input);
            wrap.appendChild(icon);
            wrap.appendChild(input);

            input.dataset.modernSearchReady = '1';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        applyModernSearchUI(document);

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (!node || node.nodeType !== 1) return;
                    applyModernSearchUI(node);
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    });
})();
</script>

<script>
(function () {
    function resolveActionTone(el) {
        const classList = el.classList;
        if (classList.contains('apply')) return 'is-apply';
        if (classList.contains('reset')) return 'is-reset';
        if (classList.contains('add')) return 'is-add';
        if (classList.contains('import') || classList.contains('export') || classList.contains('download-report')) return 'is-export';
        if (classList.contains('view')) return 'is-view';
        if (classList.contains('edit')) return 'is-edit';
        if (classList.contains('convert')) return 'is-convert';
        if (classList.contains('payment')) return 'is-payment';
        if (classList.contains('idcard')) return 'is-idcard';
        if (classList.contains('delete') || classList.contains('btn-danger')) return 'is-delete';
        if (classList.contains('done')) return 'is-done';
        return 'is-default';
    }

    function isIconOnly(el) {
        const text = (el.textContent || '').replace(/\s+/g, ' ').trim();
        const hasIcon = !!el.querySelector('i');
        return hasIcon && text.length <= 2;
    }

    function applyUniversalActionButtons(root) {
        const scope = root || document;
        const selector = '.action-btn, .btn-icon-only, .ssr-action-btn, .intern-action-btn, .iso-report-action-btn';

        scope.querySelectorAll(selector).forEach(function (el) {
            if (!el || el.dataset.universalActionReady === '1') return;
            if (el.closest('.sidebar')) return;

            const tone = resolveActionTone(el);
            el.classList.add('crm-action-btn', tone);

            if (isIconOnly(el) || el.classList.contains('btn-icon-only')) {
                el.classList.add('crm-action-icon');
            }

            el.dataset.universalActionReady = '1';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        applyUniversalActionButtons(document);

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (!node || node.nodeType !== 1) return;
                    applyUniversalActionButtons(node);
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    });
})();
</script>

<script>
(function () {
    let tooltipEl = null;
    let activeTarget = null;
    function detectTouchDevice() {
        return !!(
            window.matchMedia('(hover: none), (pointer: coarse)').matches ||
            ('ontouchstart' in window) ||
            (navigator.maxTouchPoints && navigator.maxTouchPoints > 0)
        );
    }

    let isTouchDevice = detectTouchDevice();

    function getTooltipText(el) {
        return (el.getAttribute('data-modern-tooltip') || el.getAttribute('data-tooltip') || el.getAttribute('aria-label') || '').trim();
    }

    function ensureTooltip() {
        if (tooltipEl) return tooltipEl;
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'floating-ui-tooltip';
        tooltipEl.setAttribute('data-placement', 'top');
        tooltipEl.innerHTML = '<div class="floating-ui-tooltip__bubble"></div><div class="floating-ui-tooltip__arrow"></div>';
        document.body.appendChild(tooltipEl);
        return tooltipEl;
    }

    function positionTooltip(el) {
        const tooltip = ensureTooltip();
        const bubble = tooltip.querySelector('.floating-ui-tooltip__bubble');
        const text = getTooltipText(el);
        if (!bubble || !text) return;

        bubble.textContent = text;
        tooltip.classList.add('is-visible');

        const gap = 10;
        const rect = el.getBoundingClientRect();
        const tipRect = tooltip.getBoundingClientRect();
        const vw = window.innerWidth || document.documentElement.clientWidth || 0;
        const vh = window.innerHeight || document.documentElement.clientHeight || 0;

        let placement = 'top';
        let top = rect.top - tipRect.height - gap;

        if (top < 8) {
            placement = 'bottom';
            top = rect.bottom + gap;
        }

        if (top + tipRect.height > vh - 8) {
            top = Math.max(8, vh - tipRect.height - 8);
        }

        let left = rect.left + (rect.width / 2) - (tipRect.width / 2);
        left = Math.max(8, Math.min(left, vw - tipRect.width - 8));

        tooltip.setAttribute('data-placement', placement);
        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
    }

    function showTooltip(el) {
        if (isTouchDevice) return;
        if (!el) return;
        if (!getTooltipText(el)) return;
        activeTarget = el;
        positionTooltip(el);
    }

    function hideTooltip(el) {
        if (el && activeTarget && el !== activeTarget) return;
        if (!tooltipEl) return;
        activeTarget = null;
        tooltipEl.classList.remove('is-visible');
    }

    function normalizeTitle(el) {
        if (!el || el.dataset.tooltipReady === '1') return;
        const title = (el.getAttribute('title') || '').trim();
        if (title && !el.getAttribute('data-tooltip') && !el.getAttribute('data-modern-tooltip')) {
            el.setAttribute('data-tooltip', title);
        }
        const tooltipText = getTooltipText(el);
        if (tooltipText && !el.getAttribute('aria-label')) {
            el.setAttribute('aria-label', tooltipText);
        }
        if (tooltipText && shouldUseMobileLabel(el) && !el.getAttribute('data-mobile-label')) {
            el.setAttribute('data-mobile-label', tooltipText);
        }
        if (title) {
            el.removeAttribute('title');
        }
        el.dataset.tooltipReady = '1';
    }

    function shouldUseMobileLabel(el) {
        if (!el || !el.classList) return false;
        if (el.closest('.sidebar')) return false;
        if (el.classList.contains('menu-toggle')) return false;
        if (
            el.classList.contains('btn-icon-only') ||
            el.classList.contains('action-btn') ||
            el.classList.contains('crm-icon-btn') ||
            el.classList.contains('sa-icon-btn') ||
            el.classList.contains('source-icon') ||
            el.classList.contains('ui-tooltip') ||
            el.classList.contains('tooltip')
        ) {
            return true;
        }

        const text = ((el.textContent || '').replace(/\s+/g, '')).trim();
        const hasIcon = !!el.querySelector('i, svg');
        return hasIcon && text.length <= 2;
    }

    function bindTooltip(el) {
        if (!el || el.dataset.floatingTooltipBound === '1') return;
        normalizeTitle(el);
        if (!getTooltipText(el)) return;
        if (isTouchDevice) {
            el.dataset.floatingTooltipBound = '1';
            return;
        }

        el.dataset.floatingTooltipBound = '1';
        el.addEventListener('mouseenter', function () { showTooltip(el); });
        el.addEventListener('mouseleave', function () { hideTooltip(el); });
        el.addEventListener('focus', function () { showTooltip(el); });
        el.addEventListener('blur', function () { hideTooltip(el); });
    }

    function fallbackLabel(el) {
        if (el.classList.contains('apply')) return 'Apply';
        if (el.classList.contains('reset')) return 'Reset';
        if (el.classList.contains('add')) return 'Add';
        if (el.classList.contains('import')) return 'Import';
        if (el.classList.contains('export')) return 'Export';
        if (el.classList.contains('view')) return 'View';
        if (el.classList.contains('edit')) return 'Edit';
        if (el.classList.contains('delete')) return 'Delete';
        if (el.classList.contains('convert')) return 'Convert';
        return '';
    }

    function applyMobileActionLabels(root) {
        if (!isTouchDevice) return;
        const scope = root || document;
        const selector = '[data-tooltip], [data-modern-tooltip], [aria-label], .btn-icon-only, .action-btn, .crm-icon-btn, .sa-icon-btn, .source-icon, .ui-tooltip, .tooltip';
        const nodes = scope.matches && scope.matches(selector) ? [scope] : Array.from(scope.querySelectorAll(selector));

        nodes.forEach(function (el) {
            if (!shouldUseMobileLabel(el)) return;
            const label = (el.getAttribute('data-mobile-label') || getTooltipText(el) || el.getAttribute('aria-label') || fallbackLabel(el) || '').trim();
            if (!label) return;
            el.setAttribute('data-mobile-label', label);
        });
    }

    function initTooltips(root) {
        const scope = root || document;
        const selector = '[data-tooltip], [data-modern-tooltip], .ui-tooltip, .tooltip, [title]';
        scope.querySelectorAll(selector).forEach(bindTooltip);
        if (scope.matches && scope.matches(selector)) {
            bindTooltip(scope);
        }
        applyMobileActionLabels(scope);
    }

    window.initializeFloatingTooltips = initTooltips;

    document.addEventListener('DOMContentLoaded', function () {
        document.documentElement.classList.toggle('touch-ui', isTouchDevice);
        initTooltips(document);

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (!node || node.nodeType !== 1) return;
                    initTooltips(node);
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    });

    window.addEventListener('scroll', function () {
        if (activeTarget) positionTooltip(activeTarget);
    }, true);

    window.addEventListener('resize', function () {
        if (activeTarget) positionTooltip(activeTarget);
    });
})();
</script>

<!-- ============================= -->
<!-- Flash Messages -->
<!-- ============================= -->

<script>
(function () {
    function getToastColors(icon) {
        if (icon === 'success') return { bg: '#ecfdf3', border: '#86efac', color: '#166534' };
        if (icon === 'error') return { bg: '#fef2f2', border: '#fca5a5', color: '#991b1b' };
        if (icon === 'warning') return { bg: '#fff7ed', border: '#fdba74', color: '#9a3412' };
        return { bg: '#eff6ff', border: '#93c5fd', color: '#1e3a8a' };
    }

    window.crmToast = function (opts) {
        const options = opts || {};
        const icon = options.icon || 'info';
        const title = options.title || '';
        const text = options.text || '';
        const colors = getToastColors(icon);

        return Swal.fire({
            toast: options.toast !== false,
            position: options.position || 'top-end',
            showConfirmButton: options.showConfirmButton || false,
            timer: options.timer || 2800,
            timerProgressBar: options.timerProgressBar !== false,
            icon: icon,
            title: title,
            text: text,
            confirmButtonColor: '#e91e63',
            customClass: { popup: 'crm-swal-popup' },
            background: colors.bg,
            color: colors.color,
            didOpen: function (popup) {
                popup.style.border = '1px solid ' + colors.border;
                popup.style.borderRadius = '12px';
                popup.style.boxShadow = '0 10px 24px rgba(15,23,42,.14)';
            }
        });
    };
})();
</script>

<?php if (function_exists('getFlash') && ($success = getFlash('success'))): ?>
<script>
document.addEventListener("DOMContentLoaded", function(){
    crmToast({
        icon: 'success',
        title: 'Success',
        text: <?= json_encode($success) ?>
    });
});
</script>
<?php endif; ?>

<?php if (function_exists('getFlash') && ($error = getFlash('error'))): ?>
<script>
document.addEventListener("DOMContentLoaded", function(){
    crmToast({
        icon: 'error',
        title: 'Error',
        text: <?= json_encode($error) ?>
    });
});
</script>
<?php endif; ?>

<!-- ============================= -->
<!-- Sidebar Toggle Script -->
<!-- ============================= -->

<script>
document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll(".menu-toggle").forEach(function(btn){

        btn.addEventListener("click", function(){

            const li = btn.closest("li");
            const submenu = li.querySelector(".submenu");

            if (!submenu) return;

            const isOpen = li.classList.contains("open");

            document.querySelectorAll(".sidebar li.has-children.open").forEach(function(other){
                if (other !== li) {
                    other.classList.remove("open");
                    const otherToggle = other.querySelector(".menu-toggle");
                    if (otherToggle) otherToggle.setAttribute("aria-expanded", "false");
                    const sm = other.querySelector(".submenu");
                    if (sm) {
                        sm.style.display = "none";
                        sm.setAttribute("hidden", "");
                    }
                }
            });

            if (isOpen) {
                li.classList.remove("open");
                submenu.style.display = "none";
                submenu.setAttribute("hidden", "");
                btn.setAttribute("aria-expanded", "false");
            } else {
                li.classList.add("open");
                submenu.style.display = "flex";
                submenu.style.flexDirection = "column";
                submenu.style.gap = "6px";
                submenu.removeAttribute("hidden");
                btn.setAttribute("aria-expanded", "true");
            }

        });

    });

});
</script>

<!-- ============================= -->
<!-- Sidebar Menu Search -->
<!-- ============================= -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("sidebarMenuSearch");
    const sidebar = document.getElementById("crmSidebar");
    if (!searchInput || !sidebar) return;

    const topItems = Array.from(sidebar.querySelectorAll(".menu-list > li"));

    function setItemVisibility(item, visible) {
        item.style.display = visible ? "" : "none";
    }

    function applySearch(query) {
        const q = query.trim().toLowerCase();

        topItems.forEach(function (item) {
            if (item.classList.contains("sidebar-logout")) {
                setItemVisibility(item, true);
                return;
            }

            const hasChildren = item.classList.contains("has-children");

            if (!hasChildren) {
                const text = (item.textContent || "").toLowerCase();
                setItemVisibility(item, q === "" || text.includes(q));
                return;
            }

            const toggleText = (item.querySelector(".menu-toggle")?.textContent || "").toLowerCase();
            const submenu = item.querySelector(".submenu");
            const childItems = Array.from(item.querySelectorAll(".submenu > li"));
            const parentMatch = q === "" || toggleText.includes(q);

            let anyChildVisible = false;
            childItems.forEach(function (child) {
                const childText = (child.textContent || "").toLowerCase();
                const childMatch = q === "" || parentMatch || childText.includes(q);
                child.style.display = childMatch ? "" : "none";
                if (childMatch) anyChildVisible = true;
            });

            const showParent = parentMatch || anyChildVisible;
            setItemVisibility(item, showParent);

            if (!submenu) return;

            if (q === "") {
                const initiallyOpen = item.getAttribute("data-initial-open") === "1";
                item.classList.toggle("open", initiallyOpen);
                submenu.style.display = initiallyOpen ? "flex" : "none";
                if (initiallyOpen) {
                    submenu.removeAttribute("hidden");
                } else {
                    submenu.setAttribute("hidden", "");
                }
                const toggle = item.querySelector(".menu-toggle");
                if (toggle) toggle.setAttribute("aria-expanded", initiallyOpen ? "true" : "false");
                return;
            }

            if (showParent) {
                item.classList.add("open");
                submenu.style.display = "flex";
                submenu.style.flexDirection = "column";
                submenu.style.gap = "6px";
                submenu.removeAttribute("hidden");
                const toggle = item.querySelector(".menu-toggle");
                if (toggle) toggle.setAttribute("aria-expanded", "true");
            }
        });
    }

    searchInput.addEventListener("input", function () {
        applySearch(searchInput.value || "");
    });
});
</script>

<!-- ============================= -->
<!-- Mobile Sidebar Toggle -->
<!-- ============================= -->

<script>
document.addEventListener("DOMContentLoaded", function () {

  const sidebar = document.getElementById("crmSidebar");
  const toggle  = document.getElementById("sidebarToggle");
  const content = document.querySelector(".content");

  if (!sidebar || !toggle) return;

  let overlay = document.getElementById("sidebarOverlay");

  if (!overlay) {
    overlay = document.createElement("div");
    overlay.id = "sidebarOverlay";
    overlay.className = "sidebar-overlay";
    document.body.appendChild(overlay);
  }

  function isMobile() {
    return window.innerWidth <= 1024;
  }

  function openMobile() {
    sidebar.classList.add("mobile-active");
    document.body.classList.add("sidebar-open");
  }

  function closeMobile() {
    sidebar.classList.remove("mobile-active");
    document.body.classList.remove("sidebar-open");
  }

  toggle.addEventListener("click", function () {

    if (isMobile()) {

        if (sidebar.classList.contains("mobile-active")) {
            closeMobile();
        } else {
            openMobile();
        }

    } else {

        sidebar.classList.toggle("collapsed");

        if (content) {
            content.classList.toggle("expanded");
        }

    }

  });

  overlay.addEventListener("click", closeMobile);

  sidebar.addEventListener("click", function (e) {
    const menuLink = e.target.closest(".menu-list a[href]");
    if (!menuLink) return;
    if (isMobile()) closeMobile();
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeMobile();
  });

  window.addEventListener("resize", function () {
    if (!isMobile()) closeMobile();
  });

});
</script>



<script>
$(document).ready(function(){

    if($('#createReportForm').length){

        $('#createReportForm').on('submit', function(e){
            e.preventDefault();

            let formData = $(this).serialize();

            $.ajax({
                url: 'ajax/reports/create_report.php',
                type: 'POST',
                data: formData,
                success: function(res){

                    if(res.status === 'success' || res.status === 'exists'){

                        Swal.fire({
                            icon: res.status === 'exists' ? 'info' : 'success',
                            title: res.status === 'exists' ? 'Already Exists' : 'Success',
                            text: res.message,
                            timer: 1200,
                            showConfirmButton: false
                        });

                        setTimeout(function(){
                            window.location = 'index.php?page=reports/create&report_id=' + res.report_id;
                        }, 1200);

                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }

                },
                error: function(){
                    Swal.fire('Error','Server error','error');
                }
            });

        });

    }

});
</script>


<script>
$(document).ready(function(){

    /* =========================
       CREATE REPORT (AJAX)
    ========================== */
    if($('#createReportForm').length){

        $('#createReportForm').on('submit', function(e){
            e.preventDefault();

            let formData = $(this).serialize();

            $.ajax({
                url: 'ajax/reports/create_report.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(res){

                    if(res.status === 'success' || res.status === 'exists'){

                        Swal.fire({
                            icon: res.status === 'exists' ? 'info' : 'success',
                            title: res.status === 'exists' ? 'Already Exists' : 'Success',
                            text: res.message,
                            timer: 1200,
                            showConfirmButton: false
                        });

                        setTimeout(function(){
                            window.location = 'index.php?page=reports/create&report_id=' + res.report_id;
                        }, 1200);

                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }

                },
                error: function(){
                    Swal.fire('Error','Server error','error');
                }
            });

        });

    }

    /* =========================
       ACTIVITY SAVE (AJAX)
    ========================== */
    $(document).on('submit','#activityForm',function(e){
        e.preventDefault();

        let formData = $(this).serialize();

        $.ajax({
            url: 'ajax/reports/save_activity.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res){

                if(res.status === 'success'){

                    Swal.fire({
                        icon: 'success',
                        title: 'Saved',
                        text: res.message,
                        timer: 1200,
                        showConfirmButton: false
                    });

                    let reportId = $('input[name="report_id"]').val();

                    setTimeout(function(){
                        window.location = 'index.php?page=reports/create&report_id=' + reportId;
                    }, 1200);

                } else {
                    Swal.fire('Error', res.message, 'error');
                }

            },
            error: function(){
                Swal.fire('Error','Server error','error');
            }
        });

    });

});


/* =========================
   REGISTRATION MODULE
========================= */

$(document).on('click','#addRow',function(){

    $('#regBody').append(`
    <tr>
        <td><input name="name[]" class="form-control"></td>
        <td><input name="department[]" class="form-control"></td>
        <td><input name="contact_no[]" class="form-control"></td>
        <td><input name="college[]" class="form-control"></td>
        <td><input type="date" name="reg_date[]" class="form-control"></td>
        <td><input name="course[]" class="form-control"></td>
        <td><input name="billing[]" class="form-control"></td>
        <td><input name="collection[]" class="form-control"></td>
        <td><input name="balance[]" class="form-control"></td>
        <td><input name="payment_mode[]" class="form-control"></td>
        <td><button type="button" class="btn btn-danger removeRow">X</button></td>
    </tr>
    `);

});

$(document).on('click','.removeRow',function(){
    $(this).closest('tr').remove();
});

$(document).on('submit','#registrationForm',function(e){
    e.preventDefault();

    let formData = $(this).serialize();

    $.post('ajax/reports/save_registration.php', formData, function(res){

        if(res.status === 'success'){

            Swal.fire({
                icon:'success',
                title:'Saved',
                text:res.message,
                timer:1200,
                showConfirmButton:false
            });

            let reportId = $('input[name="report_id"]').val();

            setTimeout(()=>{
                window.location='index.php?page=reports/create&report_id='+reportId;
            },1200);

        } else {
            Swal.fire('Error',res.message,'error');
        }

    },'json');
});


/* =========================
   HOURLY MODULE
========================= */

$(document).on('click','#addHourlyRow',function(){

    $('#hourlyBody').append(`
    <tr>
        <td><input name="time_slot[]" class="form-control"></td>
        <td><input name="particulars[]" class="form-control"></td>
        <td><input name="activities[]" class="form-control"></td>
        <td><button type="button" class="btn btn-danger removeRow">X</button></td>
    </tr>
    `);

});

$(document).on('submit','#hourlyForm',function(e){
    e.preventDefault();

    let formData = $(this).serialize();

    $.post('ajax/reports/save_hourly.php', formData, function(res){

        if(res.status === 'success'){

            Swal.fire({
                icon:'success',
                title:'Saved',
                text:res.message,
                timer:1200,
                showConfirmButton:false
            });

            let reportId = $('input[name="report_id"]').val();

            setTimeout(()=>{
                window.location='index.php?page=reports/create&report_id='+reportId;
            },1200);

        } else {
            Swal.fire('Error',res.message,'error');
        }

    },'json');

});




$(function(){

/* SEARCH */
$('#searchCollege').keyup(function(){

    let q = $(this).val();

    if(q.length < 2){
        $('#searchResults').html('');
        return;
    }

    $.get('ajax/reports/search_contacts.php?q='+q,function(res){

        let html = '';

        res.forEach(function(item){

            html += `
            <div class="list-group-item search-item"
                data-id="${item.id}"
                data-name="${item.name}">
                ${item.name}
            </div>`;
        });

        html += `
        <div class="list-group-item add-new text-primary">
            ➕ Add New Contact
        </div>`;

        $('#searchResults').html(html);

    },'json');

});

/* SELECT */
$(document).on('click','.search-item',function(){

    let id = $(this).data('id');
    let name = $(this).data('name');

    addRow(id,name);

    $('#searchResults').html('');
    $('#searchCollege').val('');

});

/* ADD NEW */
$(document).on('click','.add-new',function(){

    let name = prompt("Enter Name");

    if(!name) return;

    $.post('ajax/reports/add_contact.php',{name:name},function(res){

        if(res.status==='success'){
            addRow(res.id,name);
        }

    },'json');

});

/* ADD ROW */
function addRow(id,name){

    let row = `
    <tr>
        <td>
            ${name}
            <input type="hidden" name="ref_id[]" value="${id}">
        </td>
        <td><input name="status[]" class="form-control"></td>
        <td><input name="remarks[]" class="form-control"></td>
        <td><input type="date" name="follow_date[]" class="form-control"></td>
        <td><button type="button" class="btn btn-danger remove">X</button></td>
    </tr>`;

    $('#followupTable').append(row);
}

/* REMOVE */
$(document).on('click','.remove',function(){
    $(this).closest('tr').remove();
});

/* SAVE */
$('#followupForm').submit(function(e){
    e.preventDefault();

    $.post('ajax/reports/save_followups.php', $(this).serialize(), function(res){

        if(res.status==='success'){
            Swal.fire('Success',res.message,'success');
        }else{
            Swal.fire('Error',res.message,'error');
        }

    },'json');

});

});
</script>

<script>
(function () {
    const MOBILE_CARD_BREAKPOINT = 1024;

    function cleanHeaderText(text) {
        return (text || '')
            .replace(/\s+/g, ' ')
            .replace(/[^\x20-\x7E]/g, '')
            .trim();
    }

    function resolveHeaders(table) {
        const directHeaders = table.querySelectorAll('thead th');
        if (directHeaders.length) {
            return Array.from(directHeaders).map(function (th) {
                return cleanHeaderText(th.textContent);
            });
        }

        const fallbackRow = table.querySelector('tbody tr');
        if (!fallbackRow) return [];

        return Array.from(fallbackRow.children).map(function (_, idx) {
            return 'Column ' + (idx + 1);
        });
    }

    function applyMobileCardLabels(root) {
        if (window.innerWidth > MOBILE_CARD_BREAKPOINT) return;

        const scope = root || document;
        const selector = '.main-content table:not(.no-mobile-cards):not(.no-card-mobile)';
        const tables = scope.matches && scope.matches(selector)
            ? [scope]
            : Array.from(scope.querySelectorAll(selector));

        tables.forEach(function (table) {
            if (!table || table.closest('.dataTables_scrollHead, .dataTables_scrollFoot')) return;

            const headers = resolveHeaders(table);
            if (!headers.length) return;

            table.querySelectorAll('tbody tr').forEach(function (tr) {
                const cells = tr.querySelectorAll('td');
                cells.forEach(function (td, idx) {
                    if (!td.getAttribute('data-label')) {
                        td.setAttribute('data-label', headers[idx] || ('Column ' + (idx + 1)));
                    }

                    const hasWrapper = Array.from(td.children).some(function (el) {
                        return el.classList && el.classList.contains('crm-card-value');
                    });

                    if (!hasWrapper) {
                        const valueWrap = document.createElement('div');
                        valueWrap.className = 'crm-card-value';

                        const nodes = Array.from(td.childNodes);
                        nodes.forEach(function (node) {
                            valueWrap.appendChild(node);
                        });

                        td.appendChild(valueWrap);
                    }
                });
            });
        });
    }

    function run() {
        applyMobileCardLabels(document);
    }

    document.addEventListener('DOMContentLoaded', function () {
        run();

        let resizeTimer = null;
        window.addEventListener('resize', function () {
            if (resizeTimer) window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(run, 150);
        });

        const observer = new MutationObserver(function (mutations) {
            if (window.innerWidth > MOBILE_CARD_BREAKPOINT) return;
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (!node || node.nodeType !== 1) return;
                    applyMobileCardLabels(node);
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });

        window.addEventListener('resize', function () {
            isTouchDevice = detectTouchDevice();
            document.documentElement.classList.toggle('touch-ui', isTouchDevice);
            applyMobileActionLabels(document);
        });
    });
})();
</script>

</body>
</html>
