import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

    static targets = ["lightModeTrigger","darkModeTrigger"]

    // Hilfsfunktion zur korrekten Umschaltung der Icons
    setTriggerVisibility(isDarkMode) {
        if (isDarkMode) {
            // Dark Mode ist aktiv: Zeige den Light Mode Umschalter (Sonne)
            this.lightModeTriggerTarget.classList.remove('hidden');
            // Verstecke den Dark Mode Umschalter (Mond)
            this.darkModeTriggerTarget.classList.add('hidden');
        } else {
            // Light Mode ist aktiv: Zeige den Dark Mode Umschalter (Mond)
            this.lightModeTriggerTarget.classList.add('hidden');
            // Verstecke den Light Mode Umschalter (Sonne)
            this.darkModeTriggerTarget.classList.remove('hidden');
        }
    }

    initialize() {
        // Verwende 'theme' anstelle von 'darkMode' für Konsistenz mit dem FOUC-Skript
        const theme = localStorage.getItem('theme');
        const isDarkMode = theme === 'dark';

        if (isDarkMode) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // Aktualisiere die Sichtbarkeit der Umschalt-Icons basierend auf dem aktuellen Theme
        this.setTriggerVisibility(isDarkMode);
    }

    toggleDarkMode(event) {
        event.preventDefault();

        const html = document.documentElement;
        const currentTheme = localStorage.getItem('theme');
        let newTheme;

        // Wenn das aktuelle Theme nicht 'dark' ist (d.h. 'light' oder null)
        if (currentTheme !== 'dark') {
            // Wechsel zu Dark Mode
            html.classList.add('dark');
            newTheme = 'dark';
        } else {
            // Wechsel zu Light Mode
            html.classList.remove('dark');
            newTheme = 'light';
        }

        localStorage.setItem('theme', newTheme);
        this.setTriggerVisibility(newTheme === 'dark');
    }
}
