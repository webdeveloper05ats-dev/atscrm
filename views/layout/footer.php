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

</body>
</html>
