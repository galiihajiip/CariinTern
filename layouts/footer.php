        <footer class="border-top bg-white text-muted py-3 mt-4">
            <div class="container-fluid d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <span>&copy; <?= date('Y'); ?> <?= sanitize(APP_NAME); ?>. All rights reserved.</span>
                <span class="small">CariinTern</span>
            </div>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?= rtrim(BASE_URL, '/'); ?>/assets/js/custom.js"></script>
<script>
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el));

    window.setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            const instance = bootstrap.Alert.getOrCreateInstance(alert);
            instance.close();
        });
    }, 5000);
</script>
</body>
</html>
