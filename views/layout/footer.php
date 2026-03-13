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

<!-- ============================= -->
<!-- CRM Custom Scripts -->
<!-- ============================= -->

<script src="assets/js/crm-datatable.js"></script>

<!-- ============================= -->
<!-- Flash Messages -->
<!-- ============================= -->

<?php if (function_exists('getFlash') && ($success = getFlash('success'))): ?>
<script>
document.addEventListener("DOMContentLoaded", function(){
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: <?= json_encode($success) ?>,
        confirmButtonColor: '#e91e63'
    });
});
</script>
<?php endif; ?>

<?php if (function_exists('getFlash') && ($error = getFlash('error'))): ?>
<script>
document.addEventListener("DOMContentLoaded", function(){
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: <?= json_encode($error) ?>,
        confirmButtonColor: '#e91e63'
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

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeMobile();
  });

  window.addEventListener("resize", function () {
    if (!isMobile()) closeMobile();
  });

});
</script>

</body>
</html>