(function () {
  "use strict";

  function skipInput(input) {
    if (!input) return true;
    if (input.dataset.datepicker === "off") return true;
    if (input.disabled || input.readOnly) return true;
    if (input.classList.contains("flatpickr-input")) return true;
    return false;
  }

  function pad2(n) {
    return String(n).padStart(2, "0");
  }

  function parseTypedDate(raw) {
    var s = String(raw || "").trim();
    if (!s) return null;

    var d, m, y, match;

    if (/^\d{8}$/.test(s)) {
      d = parseInt(s.slice(0, 2), 10);
      m = parseInt(s.slice(2, 4), 10);
      y = parseInt(s.slice(4, 8), 10);
    } else if ((match = s.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{2,4})$/))) {
      d = parseInt(match[1], 10);
      m = parseInt(match[2], 10);
      y = parseInt(match[3], 10);
      if (y < 100) y += 2000;
    } else if ((match = s.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/))) {
      y = parseInt(match[1], 10);
      m = parseInt(match[2], 10);
      d = parseInt(match[3], 10);
    } else {
      return null;
    }

    if (y < 1900 || y > 2100) return null;
    if (m < 1 || m > 12) return null;
    if (d < 1 || d > 31) return null;

    var dt = new Date(y, m - 1, d);
    if (dt.getFullYear() !== y || dt.getMonth() !== (m - 1) || dt.getDate() !== d) {
      return null;
    }

    return { y: y, m: m, d: d };
  }

  function applyTypedValue(instance) {
    if (!instance || !instance.altInput) return true;
    var raw = (instance.altInput.value || "").trim();

    if (!raw) {
      instance.clear();
      return true;
    }

    var parsed = parseTypedDate(raw);
    if (!parsed) return false;

    var normalized = parsed.y + "-" + pad2(parsed.m) + "-" + pad2(parsed.d);
    instance.setDate(normalized, true, "Y-m-d");
    return true;
  }

  function ensureMonthPanel(instance) {
    if (!instance || !instance.calendarContainer) return;
    if (instance.calendarContainer.querySelector(".fp-month-panel")) return;

    var panel = document.createElement("div");
    panel.className = "fp-month-panel";
    panel.hidden = true;

    var months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    months.forEach(function (m, idx) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "fp-month-btn";
      btn.textContent = m;
      btn.dataset.month = String(idx);
      btn.addEventListener("click", function () {
        var target = parseInt(btn.dataset.month, 10);
        instance.changeMonth(target, false);
        panel.hidden = true;
      });
      panel.appendChild(btn);
    });

    instance.calendarContainer.appendChild(panel);
  }

  function wireMonthHeader(instance) {
    if (!instance || !instance.calendarContainer) return;
    var header = instance.calendarContainer.querySelector(".flatpickr-current-month");
    var monthTrigger = instance.calendarContainer.querySelector(".flatpickr-current-month .cur-month") || header;
    var panel = instance.calendarContainer.querySelector(".fp-month-panel");
    if (!header || !monthTrigger || !panel || header.dataset.fpMonthWired === "1") return;

    header.dataset.fpMonthWired = "1";
    monthTrigger.style.cursor = "pointer";
    monthTrigger.title = "Select month";

    monthTrigger.addEventListener("click", function (e) {
      e.stopPropagation();
      var target = e.target;
      if (target && target.closest && target.closest(".numInputWrapper")) return;
      panel.hidden = !panel.hidden;
    });

    instance.calendarContainer.addEventListener("click", function (e) {
      var day = e.target && e.target.closest ? e.target.closest(".flatpickr-day") : null;
      if (day) panel.hidden = true;
    });

    document.addEventListener("click", function (e) {
      if (!instance.calendarContainer.contains(e.target)) panel.hidden = true;
    });
  }

  function initDateInput(input) {
    if (skipInput(input)) return;
    if (input._flatpickr) return;
    if (typeof window.flatpickr !== "function") return;

    var wrapper = input.closest(".modern-input-wrap");
    var staticMode = !!wrapper;

    var options = {
      dateFormat: "Y-m-d",
      altInput: true,
      altFormat: "d-m-Y",
      altInputClass: "date-modern-input",
      allowInput: true,
      disableMobile: true,
      monthSelectorType: "static",
      static: staticMode,
      appendTo: staticMode ? wrapper : undefined,
      onReady: function (selectedDates, dateStr, instance) {
        if (instance && instance.altInput) {
          instance.altInput.placeholder = "dd-mm-yyyy";
          instance.altInput.addEventListener("blur", function () {
            var ok = applyTypedValue(instance);
            if (!ok) {
              instance.altInput.value = "";
              instance.clear();
            }
          });
          instance.altInput.addEventListener("keydown", function (e) {
            if (e.key !== "Enter") return;
            var ok = applyTypedValue(instance);
            if (!ok) {
              instance.altInput.value = "";
              instance.clear();
            }
          });
        }
        ensureMonthPanel(instance);
        wireMonthHeader(instance);
      },
      onOpen: function (selectedDates, dateStr, instance) {
        ensureMonthPanel(instance);
        wireMonthHeader(instance);
        var panel = instance.calendarContainer.querySelector(".fp-month-panel");
        if (panel) panel.hidden = true;
      },
      onChange: function (selectedDates, dateStr, instance) {
        var panel = instance.calendarContainer.querySelector(".fp-month-panel");
        if (panel) panel.hidden = true;
      },
      onClose: function (selectedDates, dateStr, instance) {
        if (!instance || !instance.altInput) return;
        if (!instance.altInput.value.trim()) return;
        var ok = applyTypedValue(instance);
        if (!ok) {
          instance.altInput.value = "";
          instance.clear();
        }
      }
    };

    window.flatpickr(input, options);
  }

  function initAll(root) {
    (root || document).querySelectorAll('input[type="date"]').forEach(initDateInput);
  }

  document.addEventListener("DOMContentLoaded", function () {
    initAll(document);

    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (m) {
        m.addedNodes.forEach(function (n) {
          if (!n || n.nodeType !== 1) return;
          if (n.matches && n.matches('input[type="date"]')) {
            initDateInput(n);
          }
          if (n.querySelectorAll) initAll(n);
        });
      });
    });

    observer.observe(document.body, { childList: true, subtree: true });
  });
})();
