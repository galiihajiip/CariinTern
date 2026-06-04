(function () {
    const baseUrl = document.documentElement.dataset.baseUrl || '';
    const serviceWorkerUrl = `${baseUrl}/sw.js`;
    const vapidPublicKey = document.documentElement.dataset.vapidPublicKey || '';
    let deferredInstallPrompt = null;
    let refreshing = false;

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', async () => {
            try {
                const registration = await navigator.serviceWorker.register(serviceWorkerUrl);

                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    if (!newWorker) {
                        return;
                    }

                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            showUpdateToast(registration);
                        }
                    });
                });
            } catch (error) {
                console.error('Service worker registration failed', error);
            }
        });

        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (refreshing) {
                return;
            }

            refreshing = true;
            window.location.reload();
        });
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;

        const dismissedUntil = Number(localStorage.getItem('cariintern-install-dismissed-until') || 0);
        if (Date.now() > dismissedUntil) {
            showInstallBanner();
        }
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        hideInstallBanner();
    });

    window.addEventListener('online', hideOfflineBanner);
    window.addEventListener('offline', showOfflineBanner);

    if (!navigator.onLine) {
        showOfflineBanner();
    }

    window.addEventListener('beforeunload', () => {
        if (navigator.onLine) {
            sessionStorage.setItem('cariintern-last-online-url', window.location.href);
        }
    });

    window.subscribePush = async function subscribePush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            showInlineNotice('Browser belum mendukung push notification.', 'warning');
            return;
        }

        if (!vapidPublicKey) {
            showInlineNotice('VAPID public key belum dikonfigurasi.', 'warning');
            return;
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            showInlineNotice('Izin notifikasi tidak diberikan.', 'warning');
            return;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
            });

            const response = await fetch(`${baseUrl}/api/push_subscribe.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(subscription)
            });
            const result = await response.json();

            showInlineNotice(result.message || 'Notifikasi berhasil diaktifkan.', result.success ? 'success' : 'danger');
        } catch (error) {
            console.error('Push subscribe failed', error);
            showInlineNotice('Notifikasi gagal diaktifkan.', 'danger');
        }
    };

    function showInstallBanner() {
        if (document.getElementById('pwaInstallBanner')) {
            return;
        }

        const banner = document.createElement('div');
        banner.id = 'pwaInstallBanner';
        banner.className = 'position-fixed bottom-0 end-0 m-3 card border-0 shadow-lg';
        banner.style.zIndex = '1080';
        banner.style.maxWidth = '360px';
        banner.innerHTML = `
            <div class="card-body d-flex gap-3 align-items-center">
                <img src="${baseUrl}/assets/icons/icon-96x96.png" alt="" width="48" height="48" class="rounded-3">
                <div class="flex-grow-1">
                    <div class="fw-semibold">Install CariinTern</div>
                    <div class="small text-muted">Install untuk akses cepat.</div>
                    <div class="d-flex gap-2 mt-2">
                        <button type="button" class="btn btn-primary btn-sm" id="pwaInstallButton">Install</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="pwaDismissButton">Nanti Saja</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(banner);

        document.getElementById('pwaInstallButton')?.addEventListener('click', async () => {
            if (!deferredInstallPrompt) {
                hideInstallBanner();
                return;
            }

            deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            hideInstallBanner();
        });

        document.getElementById('pwaDismissButton')?.addEventListener('click', () => {
            const thirtyDays = 30 * 24 * 60 * 60 * 1000;
            localStorage.setItem('cariintern-install-dismissed-until', String(Date.now() + thirtyDays));
            hideInstallBanner();
        });
    }

    function hideInstallBanner() {
        document.getElementById('pwaInstallBanner')?.remove();
    }

    function showUpdateToast(registration) {
        if (document.getElementById('pwaUpdateToast')) {
            return;
        }

        const toast = document.createElement('div');
        toast.id = 'pwaUpdateToast';
        toast.className = 'position-fixed top-0 start-50 translate-middle-x mt-3 alert alert-info shadow d-flex align-items-center gap-3';
        toast.style.zIndex = '1080';
        toast.innerHTML = `
            <span>Versi baru tersedia.</span>
            <button type="button" class="btn btn-sm btn-primary" id="pwaUpdateButton">Perbarui Sekarang</button>
        `;
        document.body.appendChild(toast);

        document.getElementById('pwaUpdateButton')?.addEventListener('click', () => {
            registration.waiting?.postMessage({ type: 'SKIP_WAITING' });
        });
    }

    function showOfflineBanner() {
        if (document.getElementById('pwaOfflineBanner')) {
            return;
        }

        const banner = document.createElement('div');
        banner.id = 'pwaOfflineBanner';
        banner.className = 'position-fixed top-0 start-0 end-0 bg-warning text-dark text-center py-2 fw-semibold';
        banner.style.zIndex = '1090';
        banner.textContent = 'Kamu sedang offline';
        document.body.appendChild(banner);
    }

    function hideOfflineBanner() {
        document.getElementById('pwaOfflineBanner')?.remove();
    }

    function showInlineNotice(message, type) {
        const notice = document.createElement('div');
        notice.className = `position-fixed bottom-0 start-50 translate-middle-x mb-3 alert alert-${type} shadow`;
        notice.style.zIndex = '1090';
        notice.textContent = message;
        document.body.appendChild(notice);
        setTimeout(() => notice.remove(), 4500);
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }
})();
