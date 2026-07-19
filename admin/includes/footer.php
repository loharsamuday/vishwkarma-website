</div> <!-- End main-content -->

<script>
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
        if(sidebar) sidebar.classList.toggle('show');
        if(sidebarOverlay) sidebarOverlay.classList.toggle('show');
    }

    if(sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    if(sidebarCloseBtn) {
        sidebarCloseBtn.addEventListener('click', toggleSidebar);
    }
    if(sidebarOverlay) {
        sidebarOverlay.addEventListener('click', toggleSidebar);
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
