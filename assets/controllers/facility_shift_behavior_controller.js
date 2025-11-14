// facility_shift_behavior_controller.js
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["dayCheckbox", "shiftSelect", "timeFrom", "timeTo"];

    connect() {
        // Prüfen ob es eine neue Schicht ist (kein demandShift.id)
        const isNewShift = this.element.querySelector('input[name="id"]') === null;

        // Alle Checkboxen initialisieren
        this.dayCheckboxTargets.forEach(checkbox => {
            if (isNewShift) {
                // Bei neuen Schichten: Alle Tage auf true setzen
                checkbox.checked = true;
                checkbox.value = "true";
            } else {
                // Bei bestehenden Schichten: checked-Status aus HTML übernehmen
                checkbox.value = checkbox.checked ? "true" : "false";
            }
            this.updateDayLabel(checkbox);
        });

        // Alle vorhandenen Selects initialisieren
        this.shiftSelectTargets.forEach(select => this.updateShiftTimes(select));

        // Select auf den richtigen Wert setzen
        if (this.hasShiftSelectTarget) {
            const selectedOption = this.shiftSelectTarget.querySelector('option[selected]');
            if (selectedOption) {
                this.shiftSelectTarget.value = selectedOption.value;
            }
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
        const row = selectEl.closest('tr');
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
        const select = event.target;
        this.updateShiftTimes(select);
    }

    removeRow(event) {
        const button = event.target;
        const row = button.closest('tr');
        row.remove();
    }
}
