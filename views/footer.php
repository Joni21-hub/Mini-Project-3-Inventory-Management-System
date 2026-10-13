<?php
/**
 * Views: Footer Layout
 */
?>
<?php if (is_logged_in()): ?>
        <footer class="mt-auto pt-5 pb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center border-top pt-3 small text-muted">
                <div>
                    &copy; <?= date('Y') ?> <strong class="text-dark">InventarisHub Enterprise</strong>. Platform Manajemen Inventaris Modern.
                </div>
                <div class="d-flex align-items-center gap-3 mt-2 mt-sm-0">
                    <span class="stock-pill stock-pill-success py-1 px-2" style="font-size:0.72rem;">
                        <span class="stock-dot"></span> Seluruh Database Terhubung
                    </span>
                    <span class="text-secondary">v2.5 Professional</span>
                </div>
            </div>
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
