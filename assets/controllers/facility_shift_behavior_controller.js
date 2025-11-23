// facility_shift_behavior_controller.js
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [
        "dayCheckbox",
        "shiftSelect",
        "timeFrom",
        "timeTo",
        "qualificationSelect",
        "colorSelect",
        "colorPreview"
    ];

    connect() {

        window.addEventListener("pageshow", (event) => {
            if (event.persisted) {
                this.element.reload();
            }
        });

        const isNewShift = this.element.querySelector('input[name="id"]') === null;

        // Tage initialisieren
        this.dayCheckboxTargets.forEach(checkbox => {
            if (isNewShift) {
                checkbox.checked = true;
                checkbox.value = "true";
            } else {
                checkbox.value = checkbox.checked ? "true" : "false";
            }
            this.updateDayLabel(checkbox);
        });

        // Farbe initialisieren
        if (this.hasColorSelectTarget) {
            this.updateColor(this.colorSelectTarget.value);
        }

        // Select auf gespeicherten Wert setzen
        if (this.hasShiftSelectTarget) {
            const selectedOption = this.shiftSelectTarget.querySelector('option[selected]');
            if (selectedOption) {
                this.shiftSelectTarget.value = selectedOption.value;
            }
        }
    }

    // ---------------------
    // Farb-Logik
    // ---------------------
    changeColor(event) {
        const color = event.target.value;
        this.updateColor(color);
    }

    updateColor(color) {
        // Select selbst kolorieren
        if (this.hasColorSelectTarget) {
            this.colorSelectTarget.style.backgroundColor = color;
        }

        // Icon-Box einfärben
        if (this.hasColorPreviewTarget) {
            this.colorPreviewTarget.style.borderColor = color;
        }
    }

    // ---------------------
    // Tage-Checkbox Logik
    // ---------------------
    toggleDay(event) {
        const checkbox = event.target;
        checkbox.value = checkbox.checked ? "true" : "false";
        this.updateDayLabel(checkbox);
    }

    updateDayLabel(checkbox) {
        const label = checkbox.nextElementSibling;
        if (checkbox.checked) {
            label.classList.remove('inactiveDay');
            label.classList.add('activeDay');
        } else {
            label.classList.remove('activeDay');
            label.classList.add('inactiveDay');
        }
    }

    // ---------------------
    // Schichtart Logik
    // ---------------------
    updateShiftTimes(selectEl) {
        const timeFromInput = this.timeFromTarget
        const timeToInput = this.timeToTarget

        switch (selectEl.value) {
            case 'Frühschicht':
                timeFromInput.value = '06:00';
                timeToInput.value = '14:00';
                break;
            case 'Tagschicht':
                timeFromInput.value = '08:00';
                timeToInput.value = '16:00';
                break;
            case 'Spätschicht':
                timeFromInput.value = '14:00';
                timeToInput.value = '22:00';
                break;
            case 'Nachtschicht':
                timeFromInput.value = '22:00';
                timeToInput.value = '06:00';
                break;
            default:
                timeFromInput.value = '';
                timeToInput.value = '';
        }
    }

    shiftChanged(event) {
        this.updateShiftTimes(event.target);
    }

    removeRow(event) {
        // GEÄNDERT: Von closest('tr') zu closest("[data-controller='facility-shift-behavior']")
        const button = event.target;
        const card = button.closest("[data-controller='facility-shift-behavior']");
        card.remove();
    }
}
