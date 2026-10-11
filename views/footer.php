<?php
/**
 * Views: Footer Layout
 */
?>
<?php if (is_logged_in()): ?>
        <footer class="mt-auto pt-4 text-center text-muted small">
            <hr class="border-secondary-subtle">
            <p class="mb-0">
                &copy; <?= date('Y') ?> <strong>InventarisHub</strong> - Mini Project 3: Relational Database & Advanced PHP.
            </p>
        </footer>
    </main>
<?php endif; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Responsive sidebar toggle for mobile
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebarMenu');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
    }
</script>
</body>
</html>
