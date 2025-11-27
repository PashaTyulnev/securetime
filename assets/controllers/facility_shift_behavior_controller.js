// facility_shift_behavior_controller.js
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [
        "dayCheckbox",
        "shiftSelect",
        "timeFrom",
        "timeTo",
        "qualificationSelect",
        "colorInput",
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

        // Select auf gespeicherten Wert setzen
        if (this.hasShiftSelectTarget) {
            const selectedOption = this.shiftSelectTarget.querySelector('option[selected]');
            if (selectedOption) {
                this.shiftSelectTarget.value = selectedOption.value;
            }
        }
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
        const colorInput = this.colorInputTarget;

        let shiftPresets = localStorage.getItem('shiftPresets');
        shiftPresets = shiftPresets ? JSON.parse(shiftPresets) : {};

        shiftPresets = shiftPresets.member

        let selectedShiftPresetName = selectEl.value

        //sucheim shiftsPresets nach name
        let selectedShiftPreset = shiftPresets.find(preset => preset.name === selectedShiftPresetName);

        if (selectedShiftPreset) {
            // Funktion, um ISO-Zeit zu HH:MM zu konvertieren
            const formatTime = isoString => {
                if (!isoString) return '';
                const date = new Date(isoString);
                const hours = date.getHours().toString().padStart(2, '0');
                const minutes = date.getMinutes().toString().padStart(2, '0');
                return `${hours}:${minutes}`;
            }

            timeFromInput.value = formatTime(selectedShiftPreset.timeFrom);
            timeToInput.value = formatTime(selectedShiftPreset.timeTo);
            colorInput.value = selectedShiftPreset.color;
            this.shiftSelectTarget.style.backgroundColor = selectedShiftPreset.color;
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
