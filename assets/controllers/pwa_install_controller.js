import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['banner', 'installButton', 'dismissButton'];
    static values = {
        dismissed: Boolean,
        installText: String,
        dismissText: String,
        description: String
    }

    connect() {
        // Prüfe ob PWA installierbar ist und ob bereits installiert
        if (!this.isPWAInstallable()) {
            return;
        }

        // Prüfe ob bereits installiert
        if (this.isInstalled()) {
            return;
        }

        // Prüfe ob bereits abgelehnt (in localStorage)
        const dismissed = localStorage.getItem('pwa-install-dismissed');
        if (dismissed === 'true') {
            return;
        }

        // Zeige Banner nach kurzer Verzögerung (bessere UX)
        setTimeout(() => {
            this.showBanner();
        }, 3000); // 3 Sekunden Verzögerung
    }

    isPWAInstallable() {
        // Prüfe ob Browser PWA Installation unterstützt
        return 'serviceWorker' in navigator && 
               ('BeforeInstallPromptEvent' in window || 
                window.matchMedia('(display-mode: standalone)').matches === false);
    }

    isInstalled() {
        // Prüfe ob App bereits installiert ist
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.navigator.standalone === true ||
               document.referrer.includes('android-app://');
    }

    showBanner() {
        if (this.hasBannerTarget) {
            this.bannerTarget.classList.remove('hidden');
            this.bannerTarget.classList.add('animate-slide-up');
        }
    }

    hideBanner() {
        if (this.hasBannerTarget) {
            this.bannerTarget.classList.add('hidden');
        }
    }

    async install() {
        // Speichere deferredPrompt für später
        if (window.deferredPrompt) {
            window.deferredPrompt.prompt();
            const { outcome } = await window.deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                console.log('PWA wurde installiert');
            }
            
            window.deferredPrompt = null;
            this.hideBanner();
        } else {
            // Fallback für iOS Safari
            this.showIOSInstructions();
        }
    }

    dismiss() {
        // Speichere Ablehnung in localStorage
        localStorage.setItem('pwa-install-dismissed', 'true');
        this.hideBanner();
    }

    showIOSInstructions() {
        // Zeige iOS-spezifische Installationsanweisungen
        alert('Zum Installieren: Tippen Sie auf das Teilen-Symbol und wählen Sie "Zum Home-Bildschirm hinzufügen"');
    }
}
