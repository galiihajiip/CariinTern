<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/file_upload.php';

require_role('company');

$page_title = 'Profil Perusahaan';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$industries = ['Teknologi', 'Keuangan', 'Pendidikan', 'Kesehatan', 'Manufaktur', 'Retail', 'Media', 'Konsultan', 'Logistik', 'Lainnya'];
$profile = null;
$logoUrl = '';
$old = [
    'company_name' => '',
    'industry' => 'Teknologi',
    'description' => '',
    'address' => '',
    'phone' => '',
    'website' => '',
    'logo' => '',
    'is_verified' => '0',
];

function company_profile_load(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT company_profiles.*, users.email, users.name AS user_name
         FROM company_profiles
         INNER JOIN users ON users.id = company_profiles.user_id
         WHERE company_profiles.user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([':user_id' => $userId]);
    $profile = $stmt->fetch();

    return $profile ?: null;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $profile = company_profile_load($pdo, $userId);

    if (!$profile) {
        $user = get_user_by_id($userId);
        $stmt = $pdo->prepare(
            'INSERT INTO company_profiles (user_id, company_name, industry, description, address, phone)
             VALUES (:user_id, :company_name, :industry, :description, :address, :phone)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':company_name' => $user['name'] ?? 'Perusahaan Baru',
            ':industry' => '',
            ':description' => '',
            ':address' => '',
            ':phone' => '',
        ]);

        $profile = company_profile_load($pdo, $userId);
    }

    if ($profile) {
        $_SESSION['company_verified'] = (int) $profile['is_verified'] === 1;
        $old = [
            'company_name' => sanitize((string) $profile['company_name']),
            'industry' => sanitize((string) $profile['industry']),
            'description' => sanitize((string) $profile['description']),
            'address' => sanitize((string) $profile['address']),
            'phone' => sanitize((string) $profile['phone']),
            'website' => sanitize((string) ($profile['website'] ?? '')),
            'logo' => sanitize((string) ($profile['logo'] ?? '')),
            'is_verified' => (string) (int) $profile['is_verified'],
        ];

        if (!empty($profile['logo'])) {
            $logoUrl = get_file_url((string) $profile['logo'], 'company_logos');
        }
    }
} catch (PDOException $exception) {
    error_log('Load company profile failed: ' . $exception->getMessage());
    set_flash('error', 'Profil perusahaan gagal dimuat');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim((string) ($_POST['company_name'] ?? ''));
    $industry = trim((string) ($_POST['industry'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $website = trim((string) ($_POST['website'] ?? ''));
    $errors = [];
    $newLogo = $profile['logo'] ?? null;

    $old = [
        'company_name' => sanitize($companyName),
        'industry' => sanitize($industry),
        'description' => sanitize($description),
        'address' => sanitize($address),
        'phone' => sanitize($phone),
        'website' => sanitize($website),
        'logo' => sanitize((string) ($profile['logo'] ?? '')),
        'is_verified' => (string) (int) ($profile['is_verified'] ?? 0),
    ];

    if ($companyName === '') {
        $errors[] = 'Nama perusahaan wajib diisi';
    }

    if ($industry === '') {
        $errors[] = 'Industri wajib dipilih';
    }

    if ($description === '') {
        $errors[] = 'Deskripsi perusahaan wajib diisi';
    }

    if ($address === '') {
        $errors[] = 'Alamat wajib diisi';
    }

    if ($phone === '') {
        $errors[] = 'No. telepon wajib diisi';
    }

    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = 'Website harus berupa URL valid';
    }

    $hasLogoUpload = isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($errors === [] && $hasLogoUpload) {
        $validation = validate_file($_FILES['logo'], 'image');

        if (!$validation['valid']) {
            $errors[] = $validation['message'];
        } elseif ((int) ($_FILES['logo']['size'] ?? 0) > 2 * 1024 * 1024) {
            $errors[] = 'Ukuran logo tidak boleh lebih dari 2MB';
        } else {
            $upload = upload_file($_FILES['logo'], 'company_logos', 'logo_' . $userId);

            if (!$upload['success']) {
                $errors[] = $upload['message'];
            } else {
                $newLogo = $upload['filename'];

                if (!empty($profile['logo'])) {
                    delete_file((string) $profile['logo'], 'company_logos');
                }
            }
        }
    }

    if ($errors === []) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE company_profiles
                 SET company_name = :company_name,
                     industry = :industry,
                     description = :description,
                     address = :address,
                     phone = :phone,
                     website = :website,
                     logo = :logo
                 WHERE user_id = :user_id'
            );
            $stmt->execute([
                ':company_name' => $companyName,
                ':industry' => $industry,
                ':description' => $description,
                ':address' => $address,
                ':phone' => $phone,
                ':website' => $website !== '' ? $website : null,
                ':logo' => $newLogo,
                ':user_id' => $userId,
            ]);

            $_SESSION['company_verified'] = (int) ($profile['is_verified'] ?? 0) === 1;
            log_activity($userId, 'update_company_profile', 'Perusahaan memperbarui profil');
            set_flash('success', 'Profil perusahaan berhasil diperbarui');
            redirect(BASE_URL . '/company/profile.php');
        } catch (PDOException $exception) {
            error_log('Update company profile failed: ' . $exception->getMessage());
            $errors[] = 'Profil perusahaan gagal diperbarui. Silakan coba lagi nanti';
        }
    }

    foreach ($errors as $error) {
        set_flash('error', $error);
    }
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar_company.php';
?>

<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Profil Perusahaan</h1>
        <p class="text-muted mb-0">Lengkapi informasi perusahaan agar lebih mudah diverifikasi admin.</p>
    </div>
    <div>
        <?php if ($old['is_verified'] === '1'): ?>
            <span class="badge bg-success fs-6">Terverifikasi</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark fs-6">Belum Terverifikasi</span>
        <?php endif; ?>
    </div>
</div>

<?= display_flash(); ?>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Edit Profil</h2>

                <form id="profileForm" class="needs-validation" method="POST" action="profile.php" enctype="multipart/form-data" novalidate>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="companyName" class="form-label">Nama Perusahaan</label>
                            <input type="text" class="form-control" id="companyName" name="company_name" value="<?= $old['company_name']; ?>" required>
                            <div class="invalid-feedback">Nama perusahaan wajib diisi.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="industry" class="form-label">Industri</label>
                            <select class="form-select" id="industry" name="industry" required>
                                <option value="">Pilih Industri</option>
                                <?php foreach ($industries as $industry): ?>
                                    <option value="<?= sanitize($industry); ?>" <?= $old['industry'] === $industry ? 'selected' : ''; ?>>
                                        <?= sanitize($industry); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Industri wajib dipilih.</div>
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?= $old['description']; ?></textarea>
                            <div class="invalid-feedback">Deskripsi wajib diisi.</div>
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label">Alamat</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?= $old['address']; ?></textarea>
                            <div class="invalid-feedback">Alamat wajib diisi.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label">No. Telepon</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= $old['phone']; ?>" required>
                            <div class="invalid-feedback">No. telepon wajib diisi.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="website" class="form-label">Website</label>
                            <input type="url" class="form-control" id="website" name="website" value="<?= $old['website']; ?>" placeholder="https://perusahaan.com">
                            <div class="invalid-feedback">Masukkan URL website yang valid.</div>
                        </div>

                        <div class="col-12">
                            <label for="logo" class="form-label">Logo Perusahaan</label>
                            <label class="upload-area d-block text-center p-4" for="logo" id="uploadArea">
                                <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
                                <div class="fw-semibold mt-2">Klik atau drag-and-drop logo ke sini</div>
                                <div class="text-muted small">JPG, PNG, atau WebP. Maksimal 2MB.</div>
                                <div id="fileMeta" class="small text-primary mt-2"></div>
                            </label>
                            <input type="file" class="form-control d-none" id="logo" name="logo" accept="image/jpeg,image/png,image/webp">
                            <div class="invalid-feedback d-block" id="logoError"></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="<?= rtrim(BASE_URL, '/'); ?>/company/dashboard.php" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>
                            Simpan Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Preview Profil</h2>

                <div class="text-center mb-4">
                    <img
                        id="logoPreview"
                        src="<?= $logoUrl !== '' ? sanitize($logoUrl) : 'https://placehold.co/180x180?text=Logo'; ?>"
                        alt="Preview logo perusahaan"
                        class="rounded-4 border object-fit-cover"
                        style="width: 180px; height: 180px;"
                    >
                </div>

                <div class="mb-3">
                    <div class="small text-muted">Nama Perusahaan</div>
                    <div class="fw-semibold" id="previewName"><?= $old['company_name'] !== '' ? $old['company_name'] : 'Nama perusahaan'; ?></div>
                </div>

                <div class="mb-3">
                    <div class="small text-muted">Industri</div>
                    <div id="previewIndustry"><?= $old['industry'] !== '' ? $old['industry'] : '-'; ?></div>
                </div>

                <div class="mb-3">
                    <div class="small text-muted">Alamat</div>
                    <div id="previewAddress"><?= $old['address'] !== '' ? nl2br($old['address']) : '-'; ?></div>
                </div>

                <div class="mb-3">
                    <div class="small text-muted">Kontak</div>
                    <div id="previewPhone"><?= $old['phone'] !== '' ? $old['phone'] : '-'; ?></div>
                    <div id="previewWebsite" class="small text-muted"><?= $old['website'] !== '' ? $old['website'] : '-'; ?></div>
                </div>

                <div>
                    <div class="small text-muted">Deskripsi</div>
                    <p class="mb-0" id="previewDescription"><?= $old['description'] !== '' ? nl2br($old['description']) : '-'; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const form = document.getElementById('profileForm');
    const logoInput = document.getElementById('logo');
    const uploadArea = document.getElementById('uploadArea');
    const logoPreview = document.getElementById('logoPreview');
    const logoError = document.getElementById('logoError');
    const fileMeta = document.getElementById('fileMeta');
    const maxLogoSize = 2 * 1024 * 1024;
    const allowedLogoTypes = ['image/jpeg', 'image/png', 'image/webp'];

    const bindPreview = (inputId, previewId, fallback = '-') => {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);

        input.addEventListener('input', () => {
            preview.textContent = input.value.trim() || fallback;
        });
    };

    bindPreview('companyName', 'previewName', 'Nama perusahaan');
    bindPreview('industry', 'previewIndustry');
    bindPreview('address', 'previewAddress');
    bindPreview('phone', 'previewPhone');
    bindPreview('website', 'previewWebsite');
    bindPreview('description', 'previewDescription');

    function humanFileSize(size) {
        return size >= 1024 * 1024
            ? (size / (1024 * 1024)).toFixed(2) + ' MB'
            : (size / 1024).toFixed(1) + ' KB';
    }

    function handleLogoFile(file) {
        logoError.textContent = '';
        logoInput.classList.remove('is-invalid');

        if (!file) {
            fileMeta.textContent = '';
            return true;
        }

        if (!allowedLogoTypes.includes(file.type)) {
            logoError.textContent = 'Logo harus JPG, PNG, atau WebP.';
            logoInput.classList.add('is-invalid');
            return false;
        }

        if (file.size > maxLogoSize) {
            logoError.textContent = 'Ukuran logo maksimal 2MB.';
            logoInput.classList.add('is-invalid');
            return false;
        }

        fileMeta.textContent = file.name + ' (' + humanFileSize(file.size) + ')';

        const reader = new FileReader();
        reader.onload = event => {
            logoPreview.src = event.target.result;
        };
        reader.readAsDataURL(file);

        return true;
    }

    logoInput.addEventListener('change', () => {
        handleLogoFile(logoInput.files[0]);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, event => {
            event.preventDefault();
            uploadArea.classList.add('border-primary');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, event => {
            event.preventDefault();
            uploadArea.classList.remove('border-primary');
        });
    });

    uploadArea.addEventListener('drop', event => {
        const file = event.dataTransfer.files[0];
        if (!file) return;

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        logoInput.files = dataTransfer.files;
        handleLogoFile(file);
    });

    form.addEventListener('submit', event => {
        const logoValid = handleLogoFile(logoInput.files[0]);

        if (!form.checkValidity() || !logoValid) {
            event.preventDefault();
            event.stopPropagation();
        }

        form.classList.add('was-validated');
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
