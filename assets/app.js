import './bootstrap.js';
import './styles/app.css';
import {initFlowbite} from 'flowbite';

// Unterdrücke Flowbite-Warnungen über fehlende Modal-IDs
// Die Modals werden durch unsere Stimulus-Controller initialisiert
const originalWarn = console.warn;
console.warn = function(...args) {
    // Prüfe alle Argumente auf Flowbite-Modal-Warnungen
    const message = args.join(' ');
    // Ignoriere Flowbite-Modal-Warnungen (verschiedene Formulierungen)
    if (typeof message === 'string' && (
        message.includes('Modal with id') && message.includes('does not exist') ||
        message.includes('Modal with id') && message.includes('Are you sure that the data-modal-target')
    )) {
        return; // Unterdrücke diese Warnung
    }
    // Alle anderen Warnungen normal ausgeben
    originalWarn.apply(console, args);
};

// PWA Install Prompt Event Handler
window.addEventListener('beforeinstallprompt', (e) => {
    // Verhindere den Standard-Prompt
    e.preventDefault();
    // Speichere das Event für später
    window.deferredPrompt = e;
});

document.addEventListener('turbo:load', () => {
    try {
        // Initialisiere Flowbite
        // Die Modals werden durch unsere Stimulus-Controller initialisiert
        initFlowbite();
    } catch (error) {
        // Ignoriere Flowbite-Initialisierungsfehler
        console.debug('Flowbite Initialisierung:', error);
    }
});
