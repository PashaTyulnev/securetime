import { Controller } from '@hotwired/stimulus';
import { Dropdown } from 'flowbite';

export default class extends Controller {
    static targets = ['trigger', 'menu', 'label'];

    connect() {
        // Initialisiere Flowbite Dropdown für dieses Element
        const dropdownButton = this.element.querySelector('[data-dropdown-toggle]');
        const dropdownMenu = this.element.querySelector('[id$="Dropdown"]');
        
        if (dropdownButton && dropdownMenu) {
            // Initialisiere Flowbite Dropdown manuell
            this.dropdown = new Dropdown(dropdownMenu, dropdownButton);
        }
        
        // Zeige bereits ausgewählte Rollen beim Laden an
        this.updateLabel();
    }

    disconnect() {
        if (this.dropdown) {
            // Cleanup falls nötig
            this.dropdown = null;
        }
    }

    updateLabel(event = null) {
        // Aktualisiere das Label basierend auf ausgewählten Checkboxen
        const checkboxes = this.element.querySelectorAll('input[type="checkbox"]');
        const checked = Array.from(checkboxes).filter(cb => cb.checked);
        
        if (this.hasLabelTarget) {
            if (checked.length === 0) {
                // Verwende das defaultOption aus dem Button-Text oder 'Auswählen'
                const defaultText = this.element.querySelector('label').textContent.trim() || 'Auswählen';
                this.labelTarget.textContent = defaultText;
            } else if (checked.length === 1) {
                const label = checked[0].closest('label').querySelector('span').textContent.trim();
                this.labelTarget.textContent = label;
            } else {
                // Zeige alle ausgewählten Rollen an, getrennt durch Komma
                const labels = checked.map(cb => {
                    return cb.closest('label').querySelector('span').textContent.trim();
                });
                this.labelTarget.textContent = labels.join(', ');
            }
        }
    }
}

