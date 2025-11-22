import { Controller } from '@hotwired/stimulus';
import FacilityComponentApi from "../api/facility/FacilityComponentApi.js";

export default class extends Controller {
    static targets = ['positionsWrapper'];

    connect() {
        // bestehende Positionen aus Twig werden gerendert
    }

    addPosition() {
        FacilityComponentApi.getPositionComponent().then(html => {
            const template = document.createElement('div');
            template.innerHTML = html;
            const element = template.firstElementChild;

            const removeBtn = element.querySelector('.remove-position');
            if (removeBtn) {
                removeBtn.addEventListener('click', () => {
                    element.remove();
                });
            }

            this.positionsWrapperTarget.appendChild(element);
        });
    }

    removePosition(event) {
        const wrapper = event.currentTarget.closest('.position-row');
        wrapper.remove();
    }

    // Positionen als Array zurückgeben
    getPositionsData() {
        const rows = this.positionsWrapperTarget.querySelectorAll('.position-row');
        const positions = [];

        rows.forEach(row => {
            const titleInput = row.querySelector('input[name="position[title]"]');
            const shortNameInput = row.querySelector('input[name="position[shortName]"]');
            const noteInput = row.querySelector('input[name="position[note]"]');

            // Nur Positionen mit Titel hinzufügen
            if (titleInput && titleInput.value.trim()) {
                positions.push({
                    title: titleInput.value.trim(),
                    shortName: shortNameInput ? shortNameInput.value.trim() : '',
                    note: noteInput ? noteInput.value.trim() : ''
                });
            }
        });

        return positions;
    }
}
