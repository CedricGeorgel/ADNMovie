if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' })
            .then(() => console.log('Service Worker OK'))
            .catch(err => console.error('Erreur SW:', err));
    });
}

const isAndroid = /Android/i.test(navigator.userAgent);
let deferredPrompt = null;
let installTriggered = false;

const installDirect  = document.getElementById('install-direct');
const installBtn     = document.getElementById('btn-install');
const installManual  = document.getElementById('install-manual-trigger');
const fallbackText   = document.getElementById('install-fallback-text');
const fallbackList   = document.getElementById('install-fallback-list');

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    installTriggered = true;

    if (installManual) installManual.style.display = 'none';
    if (installDirect) installDirect.style.display = 'block';
});

if (installBtn) {
    installBtn.addEventListener('click', async () => {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        deferredPrompt = null;
        installDirect.style.display = 'none';
    });
}

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    if (installDirect) installDirect.style.display = 'none';
    if (installManual) installManual.style.display = 'none';
});

if (isAndroid) {
    setTimeout(() => {
        if (!installTriggered) {
            if (installManual) installManual.style.display = 'block';
            if (fallbackText) fallbackText.style.display = 'none';
            if (fallbackList) fallbackList.style.display = 'none';
        }
    }, 1500);
}
