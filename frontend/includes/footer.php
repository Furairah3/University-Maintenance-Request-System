        </main>
    </div>
</div>
<script>
// Close notification dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notifDropdown');
    if (dropdown && !e.target.closest('.btn-icon') && !e.target.closest('.notifications-dropdown')) {
        dropdown.classList.remove('show');
    }
});

// Close sidebar on mobile when clicking outside
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !e.target.closest('.sidebar') && !e.target.closest('.mobile-toggle')) {
        sidebar.classList.remove('open');
    }
});
</script>
</body>
</html>
