import './bootstrap.js';
import './styles/app.css';
import 'flowbite';

// PWA Install Prompt Event Handler
window.addEventListener('beforeinstallprompt', (e) => {
    // Verhindere den Standard-Prompt
    e.preventDefault();
    // Speichere das Event für später
    window.deferredPrompt = e;
});

document.addEventListener('turbo:load', () => {
    initFlowbite();
});
