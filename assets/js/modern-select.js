(function () {
  "use strict";

  function shouldSkip(select) {
    if (!select) return true;
    var mode = (select.dataset.modernSelect || "").toLowerCase();
    var force = mode === "on" || mode === "force";
    var inDtLength = !!select.closest(".dataTables_length");
    if (mode === "off") return true;
    if (select.classList.contains("swal-modern-select")) return true;
    if (select.closest(".dataTables_filter")) return true;
    if (select.closest(".flatpickr-calendar")) return true;
    if (select.classList.contains("flatpickr-monthDropdown-months")) return true;
    if (select.multiple) return true;
    if ((select.size || 0) > 1) return true;
    if (select.closest(".dataTables_wrapper") && !inDtLength && !force) return true;
    if (select.closest(".swal2-container")) return true;
    if (select.closest(".ms-select")) return true;
    return false;
  }

  function selectedLabel(select) {
    var opt = select.options[select.selectedIndex];
    return opt ? (opt.textContent || "").trim() : "";
  }

  function enhance(select) {
    if (shouldSkip(select) || select.dataset.msEnhanced === "1") return;

    var wrapper = document.createElement("div");
    wrapper.className = "ms-select";

    var trigger = document.createElement("button");
    trigger.type = "button";
    trigger.className = "ms-trigger";
    trigger.setAttribute("aria-haspopup", "listbox");
    trigger.setAttribute("aria-expanded", "false");
    trigger.textContent = selectedLabel(select) || "Select";

    var panel = document.createElement("div");
    panel.className = "ms-panel";
    panel.hidden = true;
    panel.style.display = "none";

    var searchWrap = document.createElement("div");
    searchWrap.className = "ms-search-wrap";

    var search = document.createElement("input");
    search.type = "text";
    search.className = "ms-search";
    search.placeholder = "Search...";
    searchWrap.appendChild(search);

    var options = document.createElement("div");
    options.className = "ms-options";
    options.setAttribute("role", "listbox");

    panel.appendChild(searchWrap);
    panel.appendChild(options);

    var parent = select.parentNode;
    parent.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    wrapper.appendChild(trigger);
    wrapper.appendChild(panel);

    select.classList.add("ms-native-select");
    select.dataset.msEnhanced = "1";

    var isPortaled = false;
    var onViewportChange = null;

    function portalPanel() {
      if (isPortaled) return;
      document.body.appendChild(panel);
      panel.style.position = "fixed";
      panel.style.zIndex = "5000";
      panel.style.margin = "0";
      panel.style.right = "auto";
      isPortaled = true;
    }

    function unportalPanel() {
      if (!isPortaled) return;
      wrapper.appendChild(panel);
      panel.style.position = "";
      panel.style.left = "";
      panel.style.top = "";
      panel.style.width = "";
      panel.style.zIndex = "";
      panel.style.margin = "";
      panel.style.right = "";
      isPortaled = false;
    }

    function positionPanel() {
      if (!isPortaled || panel.hidden) return;
      var rect = trigger.getBoundingClientRect();
      var vw = window.innerWidth || document.documentElement.clientWidth || 0;
      var vh = window.innerHeight || document.documentElement.clientHeight || 0;
      var margin = 8;
      var desiredWidth = Math.max(rect.width, 220);
      var width = Math.min(desiredWidth, Math.max(180, vw - margin * 2));

      var left = rect.left;
      if (left + width > vw - margin) left = vw - margin - width;
      if (left < margin) left = margin;

      var panelHeight = Math.min(panel.scrollHeight || 280, 320);
      var below = vh - rect.bottom;
      var above = rect.top;
      var showAbove = below < panelHeight && above > below;
      var top = showAbove
        ? Math.max(margin, rect.top - panelHeight - 6)
        : Math.min(vh - margin - panelHeight, rect.bottom + 6);

      panel.style.left = left + "px";
      panel.style.top = top + "px";
      panel.style.width = width + "px";
    }

    function bindViewportWatch() {
      if (onViewportChange) return;
      onViewportChange = function () {
        positionPanel();
      };
      window.addEventListener("resize", onViewportChange);
      window.addEventListener("scroll", onViewportChange, true);
    }

    function unbindViewportWatch() {
      if (!onViewportChange) return;
      window.removeEventListener("resize", onViewportChange);
      window.removeEventListener("scroll", onViewportChange, true);
      onViewportChange = null;
    }

    function ensureDtLengthOptions() {
      if (!select.closest(".dataTables_length")) return;
      if (select.options.length > 1) return;
      if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) return;

      var tableId = select.getAttribute("aria-controls");
      if (!tableId) return;
      var $table = window.jQuery("#" + tableId);
      if (!$table.length || !window.jQuery.fn.DataTable.isDataTable($table)) return;

      var dt = $table.DataTable();
      var settings = dt.settings()[0];
      if (!settings || !settings.aLengthMenu) return;

      var raw = settings.aLengthMenu;
      var values = Array.isArray(raw[0]) ? raw[0] : raw;
      if (!Array.isArray(values) || !values.length) return;

      var current = String(select.value || dt.page.len() || "");
      select.innerHTML = "";
      values.forEach(function (v) {
        var opt = document.createElement("option");
        opt.value = String(v);
        opt.textContent = v === -1 ? "All" : String(v);
        if (String(v) === current) opt.selected = true;
        select.appendChild(opt);
      });
    }

    function searchableOptionCount() {
      ensureDtLengthOptions();
      var count = 0;
      for (var i = 0; i < select.options.length; i++) {
        var opt = select.options[i];
        if (opt.disabled) continue;
        if ((opt.value || "").trim() === "") continue;
        count++;
      }
      return count;
    }

    function syncSearchVisibility() {
      if (select.closest(".dataTables_length")) {
        searchWrap.hidden = true;
        search.value = "";
        options.style.maxHeight = "none";
        return false;
      }
      var count = searchableOptionCount();
      var showSearch = count > 5;
      searchWrap.hidden = !showSearch;
      if (!showSearch) search.value = "";
      options.style.maxHeight = showSearch ? "220px" : "none";
      return showSearch;
    }

    function render() {
      ensureDtLengthOptions();
      options.innerHTML = "";
      var hasVisible = false;
      var canSearch = syncSearchVisibility();
      var q = canSearch ? (search.value || "").trim().toLowerCase() : "";

      for (var i = 0; i < select.options.length; i++) {
        var opt = select.options[i];
        var txt = (opt.textContent || "").trim();
        if (q && txt.toLowerCase().indexOf(q) === -1) continue;

        hasVisible = true;
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "ms-option" + (opt.selected ? " active" : "");
        btn.textContent = txt || "\u00a0";
        btn.dataset.value = opt.value;
        if (opt.disabled) btn.disabled = true;

        btn.addEventListener("click", function (e) {
          var value = e.currentTarget.dataset.value || "";
          select.value = value;
          trigger.textContent = selectedLabel(select) || "Select";
          select.dispatchEvent(new Event("change", { bubbles: true }));
          close();
          render();
        });

        options.appendChild(btn);
      }

      if (!hasVisible) {
        var empty = document.createElement("div");
        empty.className = "ms-empty";
        empty.textContent = "No results";
        options.appendChild(empty);
      }

      trigger.textContent = selectedLabel(select) || "Select";
      trigger.disabled = !!select.disabled;
    }

    function open() {
      if (trigger.disabled) return;
      portalPanel();
      wrapper.classList.add("open");
      panel.hidden = false;
      panel.style.display = "";
      trigger.setAttribute("aria-expanded", "true");
      search.value = "";
      render();
      positionPanel();
      bindViewportWatch();
      if (!searchWrap.hidden) search.focus();
    }

    function close() {
      wrapper.classList.remove("open");
      panel.hidden = true;
      panel.style.display = "none";
      trigger.setAttribute("aria-expanded", "false");
      unbindViewportWatch();
      unportalPanel();
    }

    trigger.addEventListener("click", function () {
      if (wrapper.classList.contains("open")) close();
      else open();
    });

    search.addEventListener("input", function () {
      render();
      positionPanel();
    });

    document.addEventListener("click", function (e) {
      if (!wrapper.contains(e.target) && !panel.contains(e.target)) close();
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") close();
    });

    select.addEventListener("change", function () {
      trigger.textContent = selectedLabel(select) || "Select";
      render();
      positionPanel();
    });

    var observer = new MutationObserver(function () {
      render();
      positionPanel();
    });
    observer.observe(select, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ["disabled"]
    });

    render();
  }

  function init(root) {
    (root || document).querySelectorAll("select").forEach(enhance);
  }

  document.addEventListener("DOMContentLoaded", function () {
    init(document);
    var bodyObserver = new MutationObserver(function (mutations) {
      mutations.forEach(function (m) {
        m.addedNodes.forEach(function (n) {
          if (n.nodeType !== 1) return;
          if (n.matches && n.matches("select")) enhance(n);
          if (n.querySelectorAll) init(n);
        });
      });
    });
    bodyObserver.observe(document.body, { childList: true, subtree: true });
  });
})();
