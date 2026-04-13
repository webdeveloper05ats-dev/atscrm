document.addEventListener('DOMContentLoaded', function () {
    function bindRowDragToScroller(scrollBody, dragSurface) {
        if (!scrollBody || !dragSurface) return;
        if (dragSurface.dataset.dragScrollBound === '1') return;
        dragSurface.dataset.dragScrollBound = '1';

        let isDragging = false;
        let moved = false;
        let startX = 0;
        let startLeft = 0;
        let suppressClick = false;

        scrollBody.classList.add('drag-scroll');
        dragSurface.classList.add('drag-scroll-surface');

        dragSurface.addEventListener('mousedown', function (event) {
            if (event.button !== 0) return;
            if (scrollBody.scrollWidth <= scrollBody.clientWidth + 2) return;

            isDragging = true;
            moved = false;
            startX = event.clientX;
            startLeft = scrollBody.scrollLeft;
            scrollBody.classList.add('is-dragging');
            dragSurface.classList.add('is-dragging');
        });

        document.addEventListener('mousemove', function (event) {
            if (!isDragging) return;
            const deltaX = event.clientX - startX;
            if (Math.abs(deltaX) > 3) moved = true;
            if (!moved) return;

            scrollBody.scrollLeft = startLeft - deltaX;
            event.preventDefault();
        });

        document.addEventListener('mouseup', function () {
            if (!isDragging) return;
            isDragging = false;
            scrollBody.classList.remove('is-dragging');
            dragSurface.classList.remove('is-dragging');

            if (moved) {
                suppressClick = true;
                setTimeout(function () { suppressClick = false; }, 0);
            }
        });

        dragSurface.addEventListener('click', function (event) {
            if (!suppressClick) return;
            event.preventDefault();
            event.stopPropagation();
            suppressClick = false;
        }, true);
    }

    function initWrapperLevelRowDrag() {
        const wrapper = document.getElementById('targetsReportTable_wrapper');
        if (!wrapper || wrapper.dataset.rowDragBound === '1') return;
        wrapper.dataset.rowDragBound = '1';

        let activeScrollBody = null;
        let isDragging = false;
        let moved = false;
        let startX = 0;
        let startLeft = 0;
        let suppressClick = false;

        function getScrollBody() {
            return wrapper.querySelector('.dataTables_scrollBody');
        }

        wrapper.addEventListener('mousedown', function (event) {
            if (event.button !== 0) return;
            const inTable = event.target.closest('.dataTables_scrollBody table');
            if (!inTable) return;

            const scrollBody = getScrollBody();
            if (!scrollBody) return;
            if (scrollBody.scrollWidth <= scrollBody.clientWidth + 2) return;

            activeScrollBody = scrollBody;
            isDragging = true;
            moved = false;
            startX = event.clientX;
            startLeft = scrollBody.scrollLeft;

            scrollBody.classList.add('is-dragging', 'drag-scroll');
            inTable.classList.add('is-dragging', 'drag-scroll-surface');
        }, true);

        document.addEventListener('mousemove', function (event) {
            if (!isDragging || !activeScrollBody) return;
            const deltaX = event.clientX - startX;
            if (Math.abs(deltaX) > 3) moved = true;
            if (!moved) return;

            activeScrollBody.scrollLeft = startLeft - deltaX;
            event.preventDefault();
        });

        document.addEventListener('mouseup', function () {
            if (!isDragging) return;
            isDragging = false;

            if (activeScrollBody) {
                activeScrollBody.classList.remove('is-dragging');
            }

            wrapper.querySelectorAll('.drag-scroll-surface.is-dragging').forEach(function (el) {
                el.classList.remove('is-dragging');
            });

            if (moved) {
                suppressClick = true;
                setTimeout(function () { suppressClick = false; }, 0);
            }
            activeScrollBody = null;
        });

        wrapper.addEventListener('click', function (event) {
            if (!suppressClick) return;
            event.preventDefault();
            event.stopPropagation();
            suppressClick = false;
        }, true);
    }

    function initTargetsTableDragScroll() {
        const wrapper = document.getElementById('targetsReportTable_wrapper');
        const dtScrollBody = wrapper ? wrapper.querySelector('.dataTables_scrollBody') : null;
        const dtTableBody = wrapper ? wrapper.querySelector('.dataTables_scrollBody table') : null;
        const dtTableHead = wrapper ? wrapper.querySelector('.dataTables_scrollHead table') : null;

        if (dtScrollBody && dtTableBody) {
            bindRowDragToScroller(dtScrollBody, dtTableBody);
            if (dtTableHead) bindRowDragToScroller(dtScrollBody, dtTableHead);
            return;
        }

        const fallbackWrap = document.querySelector('.iso-report-table-wrap');
        const fallbackTable = document.getElementById('targetsReportTable');
        if (fallbackWrap && fallbackTable) {
            bindRowDragToScroller(fallbackWrap, fallbackTable);
        }
    }

    // Try immediate bind (works even before/without DataTables helper)
    initTargetsTableDragScroll();
    initWrapperLevelRowDrag();
    // Retry binding as DOM wrappers get created asynchronously.
    setTimeout(initTargetsTableDragScroll, 200);
    setTimeout(initTargetsTableDragScroll, 600);
    setTimeout(initWrapperLevelRowDrag, 220);
    setTimeout(initWrapperLevelRowDrag, 620);

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatMoney(value) {
        const amount = Number(value || 0);
        return 'Rs ' + amount.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatPercent(value) {
        const amount = Number(value || 0);
        return amount.toLocaleString('en-IN', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1
        }) + '%';
    }

    function statusIcon(statusClass) {
        if (statusClass === 'badge-soft-success') return 'fa-check-circle';
        if (statusClass === 'badge-soft-warning') return 'fa-clock';
        if (statusClass === 'badge-soft-danger') return 'fa-times-circle';
        if (statusClass === 'badge-soft-info') return 'fa-info-circle';
        return 'fa-circle';
    }

    function paymentStatusClass(statusText) {
        const status = String(statusText || '').toLowerCase();
        if (status === 'approved') return 'mini-status is-approved';
        if (status === 'achieved') return 'mini-status is-approved';
        if (status === 'in progress') return 'mini-status is-progress';
        if (status === 'pending') return 'mini-status is-pending';
        if (status === 'not started') return 'mini-status is-rejected';
        if (status === 'rejected') return 'mini-status is-rejected';
        return 'mini-status';
    }

    const modal = document.getElementById('isoReportViewModal');
    const modalDataNode = document.getElementById('isoReportViewData');
    const modalData = modalDataNode ? JSON.parse(modalDataNode.textContent || '{}') : {};
    const modalCloseBtn = document.getElementById('isoModalCloseBtn');
    const modalFooterCloseBtn = document.getElementById('isoModalFooterCloseBtn');

    function setProgressState(progressPercent) {
        const progressValue = Number(progressPercent || 0);
        const progressFill = document.getElementById('isoModalProgressFill');
        const width = Math.min(progressValue, 100);
        let progressClass = 'iso-progress-fill is-risk';

        if (progressValue >= 100) {
            progressClass = 'iso-progress-fill is-strong';
        } else if (progressValue >= 75) {
            progressClass = 'iso-progress-fill is-warning';
        } else if (progressValue > 0) {
            progressClass = 'iso-progress-fill';
        }

        if (progressFill) {
            progressFill.className = progressClass;
            progressFill.style.width = width + '%';
        }
    }

    function openReportModal(userId) {
        const data = modalData[String(userId)] || modalData[userId];
        if (!data || !modal) return;

        document.getElementById('isoModalUserName').textContent = data.user_name || 'Performance Details';
        document.getElementById('isoModalSubtitle').textContent = 'Review target summary, carry-forward trail, and current month collections for ' + (data.period_label || 'the selected period') + '.';
        document.getElementById('isoModalHeroName').textContent = data.user_name || '-';
        document.getElementById('isoModalHeroRole').textContent = data.role_name || '-';
        document.getElementById('isoModalHeroEmail').textContent = data.user_email || '-';
        document.getElementById('isoModalHeroPeriod').textContent = data.period_label || '-';
        document.getElementById('isoModalInfoUser').textContent = data.user_name || '-';
        document.getElementById('isoModalInfoRole').textContent = data.role_name || '-';
        document.getElementById('isoModalInfoEmail').textContent = data.user_email || '-';
        document.getElementById('isoModalInfoPeriod').textContent = data.period_label || '-';
        document.getElementById('isoModalProgressValue').textContent = formatPercent(data.progress_percent || 0);
        document.getElementById('isoModalProgressLabel').textContent = data.progress_label || 'No Target';
        document.getElementById('isoModalBaseTarget').textContent = formatMoney(data.base_target || 0);
        document.getElementById('isoModalOpeningCarry').textContent = formatMoney(data.opening_carry || 0);
        document.getElementById('isoModalEffectiveTarget').textContent = formatMoney(data.effective_target || 0);
        document.getElementById('isoModalAchieved').textContent = formatMoney(data.achieved_amount || 0);
        document.getElementById('isoModalGap').textContent = (Number(data.shortfall_amount || 0) > 0 ? 'Shortfall ' : 'Excess ') + formatMoney(Number(data.shortfall_amount || 0) > 0 ? data.shortfall_amount : data.excess_amount);
        document.getElementById('isoModalIncentiveAmount').textContent = formatMoney(data.incentive_amount || 0);
        document.getElementById('isoModalCarryRisk').textContent = data.carry_risk ? 'Yes' : 'No';
        document.getElementById('isoModalShortfall').textContent = formatMoney(data.shortfall_amount || 0);
        document.getElementById('isoModalIncentivePercent').textContent = Number(data.incentive_percent || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + '%';
        document.getElementById('isoModalInsightMessage').textContent = data.insight_message || '-';

        const statusBadge = document.getElementById('isoModalStatusBadge');
        if (statusBadge) {
            statusBadge.className = 'iso-report-badge ' + (data.status_class || 'badge-soft-secondary');
            statusBadge.innerHTML = '<i class="fas ' + statusIcon(data.status_class || '') + '"></i> ' + escapeHtml(data.status_text || 'No Target');
        }

        const downloadLink = data.download_url || '#';
        document.getElementById('isoModalDownloadBtn').setAttribute('href', downloadLink);
        document.getElementById('isoModalFooterDownloadBtn').setAttribute('href', downloadLink);

        setProgressState(data.progress_percent || 0);

        const historyTarget = document.getElementById('isoModalHistoryRows');
        const historyRows = Array.isArray(data.history_rows) ? data.history_rows : [];
        if (historyTarget) {
            historyTarget.innerHTML = historyRows.length
                ? historyRows.map(function (item) {
                    return '<tr>' +
                        '<td>' + escapeHtml(item.period || '-') + '</td>' +
                        '<td class="col-amount">' + escapeHtml(formatMoney(item.base_target || 0)) + '</td>' +
                        '<td class="col-amount">' + escapeHtml(formatMoney(item.opening_carry || 0)) + '</td>' +
                        '<td class="col-amount">' + escapeHtml(formatMoney(item.effective_target || 0)) + '</td>' +
                        '<td class="col-amount">' + escapeHtml(formatMoney(item.achieved_amount || 0)) + '</td>' +
                        '<td class="col-amount">' + escapeHtml(formatMoney(item.shortfall_amount || 0)) + '</td>' +
                        '<td class="col-status"><span class="' + paymentStatusClass(item.status_text || '') + '">' + escapeHtml(item.status_text || '-') + '</span></td>' +
                    '</tr>';
                }).join('')
                : '<tr><td colspan="7" class="iso-report-modal-empty">No history available.</td></tr>';
        }

        const collectionTarget = document.getElementById('isoModalCollectionRows');
        const collectionRows = Array.isArray(data.collection_rows) ? data.collection_rows : [];
        if (collectionTarget) {
            collectionTarget.innerHTML = collectionRows.length
                ? collectionRows.map(function (item) {
                    return '<tr>' +
                        '<td class="col-date">' + escapeHtml(item.payment_date || '-') + '</td>' +
                        '<td class="col-amount">' + escapeHtml(formatMoney(item.amount || 0)) + '</td>' +
                        '<td class="col-status"><span class="' + paymentStatusClass(item.approval_status || '') + '">' + escapeHtml(item.approval_status || '-') + '</span></td>' +
                    '</tr>';
                }).join('')
                : '<tr><td colspan="3" class="iso-report-modal-empty">No collection entries available.</td></tr>';
        }

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeReportModal() {
        if (!modal) return;
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-view-report').forEach(function (button) {
        button.addEventListener('click', function () {
            openReportModal(this.getAttribute('data-user-id'));
        });
    });

    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', closeReportModal);
    }

    if (modalFooterCloseBtn) {
        modalFooterCloseBtn.addEventListener('click', closeReportModal);
    }

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeReportModal();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && modal.classList.contains('show')) {
            closeReportModal();
        }
    });

    if (typeof crmDataTable === 'function') {
        crmDataTable('#targetsReportTable', {
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            ordering: true,
            order: [[0, 'desc']],
            searchPlaceholder: 'Search performance...',
            autoWidth: false,
            responsive: false,
            scrollX: true,
            dom:
                "<'dt-top'lf>" +
                "rt" +
                "<'iso-dt-bottom'ip>",
            columnDefs: [
                { targets: [13], orderable: false }
            ]
        });

        function syncTargetsReportTable() {
            if (window.jQuery && jQuery.fn && jQuery.fn.dataTable) {
                jQuery('#targetsReportTable').DataTable().columns.adjust().draw(false);
            }
        }

        setTimeout(function () {
            var controls = document.querySelector('#targetsReportTable_wrapper .dt-top');
            var target = document.getElementById('isoDatatableControls');
            if (controls && target) {
                target.appendChild(controls);
            }

            syncTargetsReportTable();
            initTargetsTableDragScroll();
            initWrapperLevelRowDrag();
        }, 100);

        window.addEventListener('resize', function () {
            setTimeout(syncTargetsReportTable, 50);
            setTimeout(initTargetsTableDragScroll, 120);
            setTimeout(initWrapperLevelRowDrag, 140);
        });

        window.addEventListener('load', function () {
            setTimeout(syncTargetsReportTable, 100);
            setTimeout(initTargetsTableDragScroll, 150);
            setTimeout(initWrapperLevelRowDrag, 170);
        });
    }
});
