        <footer class="border-top bg-white text-muted py-3 mt-4">
            <div class="container-fluid d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <span>&copy; <?= date('Y'); ?> <?= sanitize(APP_NAME); ?>. All rights reserved.</span>
                <span class="small">CariinTern</span>
            </div>
        </footer>
    </div>
</div>

<script src="<?= rtrim(BASE_URL, '/'); ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= rtrim(BASE_URL, '/'); ?>/assets/js/custom.js"></script>
<script src="<?= rtrim(BASE_URL, '/'); ?>/assets/js/pwa.js"></script>
<script>
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el));
</script>
</body>
</html>
