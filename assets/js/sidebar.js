document.addEventListener("DOMContentLoaded", function () {

    const toggleBtn = document.querySelector(".toggle-btn");
    const sidebar = document.querySelector(".sidebar");
    const content = document.querySelector(".content");

    toggleBtn.addEventListener("click", function () {

        if (window.innerWidth <= 768) {
            sidebar.classList.toggle("mobile-active");
        } else {
            sidebar.classList.toggle("collapsed");
            content.classList.toggle("expanded");
        }

    });

});