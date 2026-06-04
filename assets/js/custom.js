document.addEventListener('DOMContentLoaded', () => {
    initSidebarToggle();
    initAutoDismissAlerts();
    initConfirmActions();
    initCharacterCounters();
    initSlugGenerators();
    initPasswordStrengthIndicators();
    initImagePreviews();
    initFileValidators();
    initNumberFormatter();
    initDateCountdowns();
});

function initSidebarToggle() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!sidebar || !sidebarToggle || !overlay) {
        return;
    }

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-open');
        overlay.classList.toggle('show');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('sidebar-open');
        overlay.classList.remove('show');
    });

    sidebar.querySelectorAll('.nav-link').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                sidebar.classList.remove('sidebar-open');
                overlay.classList.remove('show');
            }
        });
    });
}

function initAutoDismissAlerts() {
    window.setTimeout(() => {
        document.querySelectorAll('.alert.auto-dismiss').forEach((alert) => {
            alert.classList.add('fade');
            alert.classList.remove('show');

            window.setTimeout(() => {
                if (window.bootstrap && bootstrap.Alert) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                    return;
                }

                alert.remove();
            }, 250);
        });
    }, 5000);
}

function initConfirmActions() {
    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            const message = element.getAttribute('data-confirm') || 'Apakah Anda yakin?';

            if (!window.confirm(message)) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            const form = element.closest('form');
            const tagName = element.tagName.toLowerCase();
            const type = (element.getAttribute('type') || '').toLowerCase();
            const isSubmitControl = (tagName === 'button' || tagName === 'input') && (type === '' || type === 'submit');

            if (form && !isSubmitControl) {
                event.preventDefault();
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });
    });
}

function initCharacterCounters() {
    document.querySelectorAll('textarea[data-max-chars]').forEach((textarea) => {
        const maxChars = parseInt(textarea.getAttribute('data-max-chars'), 10);

        if (!Number.isFinite(maxChars) || maxChars <= 0) {
            return;
        }

        const counter = document.createElement('div');
        counter.className = 'form-text text-end character-counter';
        textarea.insertAdjacentElement('afterend', counter);

        const updateCounter = () => {
            const length = textarea.value.length;
            counter.textContent = `${length}/${maxChars} karakter`;
            counter.classList.toggle('text-danger', length >= Math.floor(maxChars * 0.9));
            counter.classList.toggle('text-muted', length < Math.floor(maxChars * 0.9));
        };

        textarea.addEventListener('input', updateCounter);
        updateCounter();
    });
}

function initSlugGenerators() {
    document.querySelectorAll('input[data-slug-source]').forEach((slugInput) => {
        const sourceSelector = slugInput.getAttribute('data-slug-source');
        const sourceInput = sourceSelector ? document.querySelector(sourceSelector) : null;

        if (!sourceInput) {
            return;
        }

        let manuallyEdited = slugInput.value.trim() !== '';

        slugInput.addEventListener('input', () => {
            manuallyEdited = slugInput.value.trim() !== '';
        });

        sourceInput.addEventListener('input', () => {
            if (!manuallyEdited) {
                slugInput.value = generateSlug(sourceInput.value);
            }
        });
    });
}

function generateSlug(value) {
    return value
        .toString()
        .toLowerCase()
        .trim()
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

function initPasswordStrengthIndicators() {
    document.querySelectorAll('input[type="password"][data-strength="true"]').forEach((input) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'password-strength mt-2';
        wrapper.innerHTML = `
            <div class="progress" style="height: 6px;">
                <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="form-text password-strength-label">Masukkan password</div>
        `;
        input.insertAdjacentElement('afterend', wrapper);

        const bar = wrapper.querySelector('.progress-bar');
        const label = wrapper.querySelector('.password-strength-label');

        input.addEventListener('input', () => {
            const strength = getPasswordStrength(input.value);
            bar.style.width = `${strength.percent}%`;
            bar.className = `progress-bar bg-${strength.className}`;
            label.textContent = strength.label;
            label.className = `form-text password-strength-label text-${strength.className}`;
        });
    });
}

function getPasswordStrength(password) {
    let score = 0;

    if (password.length >= 8) score += 1;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score += 1;
    if (/\d/.test(password)) score += 1;
    if (/[^a-zA-Z0-9]/.test(password)) score += 1;

    if (password.length === 0) {
        return { percent: 0, className: 'secondary', label: 'Masukkan password' };
    }

    if (score <= 1) {
        return { percent: 33, className: 'danger', label: 'Weak' };
    }

    if (score <= 3) {
        return { percent: 66, className: 'warning', label: 'Medium' };
    }

    return { percent: 100, className: 'success', label: 'Strong' };
}

function initImagePreviews() {
    document.querySelectorAll('input[type="file"][data-preview]').forEach((input) => {
        const previewSelector = input.getAttribute('data-preview');
        const preview = previewSelector ? document.querySelector(previewSelector) : null;

        if (!preview) {
            return;
        }

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];

            if (!file || !file.type.startsWith('image/')) {
                return;
            }

            const reader = new FileReader();
            reader.addEventListener('load', () => {
                preview.src = reader.result;
                preview.classList.remove('d-none');
            });
            reader.readAsDataURL(file);
        });
    });
}

function initFileValidators() {
    document.querySelectorAll('input[type="file"][data-max-size], input[type="file"][data-allowed-types]').forEach((input) => {
        input.addEventListener('change', () => {
            validateFileInput(input);
        });

        const form = input.closest('form');

        if (form && !form.dataset.fileValidatorBound) {
            form.dataset.fileValidatorBound = 'true';
            form.addEventListener('submit', (event) => {
                const fileInputs = form.querySelectorAll('input[type="file"][data-max-size], input[type="file"][data-allowed-types]');
                const isValid = Array.from(fileInputs).every((fileInput) => validateFileInput(fileInput));

                if (!isValid) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            });
        }
    });
}

function validateFileInput(input) {
    clearFileError(input);

    const files = Array.from(input.files || []);

    if (files.length === 0) {
        return true;
    }

    const maxSizeMb = parseFloat(input.getAttribute('data-max-size') || '0');
    const allowedTypes = (input.getAttribute('data-allowed-types') || '')
        .split(',')
        .map((type) => type.trim().toLowerCase())
        .filter(Boolean);

    for (const file of files) {
        const extension = file.name.split('.').pop().toLowerCase();
        const sizeMb = file.size / (1024 * 1024);

        if (maxSizeMb > 0 && sizeMb > maxSizeMb) {
            showFileError(input, `Ukuran file maksimal ${maxSizeMb} MB`);
            input.value = '';
            return false;
        }

        if (allowedTypes.length > 0 && !allowedTypes.includes(extension)) {
            showFileError(input, `Tipe file harus: ${allowedTypes.join(', ')}`);
            input.value = '';
            return false;
        }
    }

    return true;
}

function showFileError(input, message) {
    const error = document.createElement('div');
    error.className = 'invalid-feedback d-block file-error';
    error.textContent = message;
    input.classList.add('is-invalid');
    input.insertAdjacentElement('afterend', error);
}

function clearFileError(input) {
    input.classList.remove('is-invalid');
    const next = input.nextElementSibling;

    if (next && next.classList.contains('file-error')) {
        next.remove();
    }
}

function initNumberFormatter() {
    document.querySelectorAll('.format-number').forEach((element) => {
        const rawValue = element.textContent.replace(/[^\d.-]/g, '');
        const number = Number(rawValue);

        if (Number.isFinite(number)) {
            element.textContent = new Intl.NumberFormat('id-ID').format(number);
        }
    });
}

function initDateCountdowns() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    document.querySelectorAll('[data-deadline]').forEach((element) => {
        const deadlineValue = element.getAttribute('data-deadline');
        const deadline = deadlineValue ? new Date(`${deadlineValue}T00:00:00`) : null;

        if (!deadline || Number.isNaN(deadline.getTime())) {
            return;
        }

        const diffDays = Math.ceil((deadline - today) / (1000 * 60 * 60 * 24));
        element.textContent = diffDays < 0 ? 'Deadline terlewat' : `${diffDays} hari lagi`;
        element.classList.remove('text-success', 'text-warning', 'text-danger');

        if (diffDays > 7) {
            element.classList.add('text-success');
        } else if (diffDays >= 3) {
            element.classList.add('text-warning');
        } else {
            element.classList.add('text-danger');
        }
    });
}
