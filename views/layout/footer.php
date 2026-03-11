<?php
// ============================================
// ATS CRM - Footer Layout (FINAL CLEAN)
// ============================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}
?>

<!-- ============================= -->
<!-- Flash Messages -->
<!-- ============================= -->
<?php if (function_exists('getFlash') && ($success = getFlash('success'))): ?>
    <div class="alert alert-success flash-message">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<?php if (function_exists('getFlash') && ($error = getFlash('error'))): ?>
    <div class="alert alert-danger flash-message">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- ============================= -->
<!-- Sidebar Toggle Script -->
<!-- ============================= -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    // Submenu collapse/expand
    document.querySelectorAll(".menu-toggle").forEach(function(btn){
        btn.addEventListener("click", function(){
            const li = btn.closest("li");
            const submenu = li.querySelector(".submenu");
            if (!submenu) return;

            const isOpen = li.classList.contains("open");

            // Close other open menus (optional)
            document.querySelectorAll(".sidebar li.has-children.open").forEach(function(other){
                if (other !== li) {
                    other.classList.remove("open");
                    const sm = other.querySelector(".submenu");
                    if (sm) sm.style.display = "none";
                }
            });

            if (isOpen) {
                li.classList.remove("open");
                submenu.style.display = "none";
            } else {
                li.classList.add("open");
                submenu.style.display = "flex";
                submenu.style.flexDirection = "column";
                submenu.style.gap = "6px";
            }
        });
    });

});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.getElementById("crmSidebar");
  const toggle  = document.getElementById("sidebarToggle");
  const content = document.querySelector(".content");

  if (!sidebar || !toggle) return;

  // Create overlay (no need to edit header.php)
  let overlay = document.getElementById("sidebarOverlay");
  if (!overlay) {
    overlay = document.createElement("div");
    overlay.id = "sidebarOverlay";
    overlay.className = "sidebar-overlay";
    document.body.appendChild(overlay);
  }

  function isMobile() {
    return window.innerWidth <= 768;
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
      // Mobile: slide in/out using your existing CSS class
      if (sidebar.classList.contains("mobile-active")) closeMobile();
      else openMobile();
    } else {
      // Desktop: collapse/expand using your existing CSS class
      sidebar.classList.toggle("collapsed");
      if (content) content.classList.toggle("expanded");
    }
  });

  // Click outside closes (mobile)
  overlay.addEventListener("click", closeMobile);

  // ESC closes (mobile)
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeMobile();
  });

  // If resized to desktop, ensure mobile state removed
  window.addEventListener("resize", function () {
    if (!isMobile()) closeMobile();
  });
});
</script>

</body>
</html>