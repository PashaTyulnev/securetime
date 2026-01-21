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
    
    static values = {
        shiftPresets: Object
    };

    connect() {
        // Speichere shiftPresets im localStorage, wenn sie als Value übergeben wurden
        if (this.hasShiftPresetsValue) {
            // shiftPresetsValue ist bereits ein Objekt mit member-Property
            const presetsData = this.shiftPresetsValue;
            // Stelle sicher, dass es die richtige Struktur hat
            if (!presetsData.member && Array.isArray(presetsData)) {
                // Falls es direkt ein Array ist, wrappe es
                localStorage.setItem('shiftPresets', JSON.stringify({ member: presetsData }));
            } else {
                localStorage.setItem('shiftPresets', JSON.stringify(presetsData));
            }
        }

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
        if (this.hasShiftSelectTarget) {
            this.shiftSelectTarget.style.backgroundColor = color;
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
        // Prüfe ob Targets existieren
        if (!this.hasTimeFromTarget || !this.hasTimeToTarget || !this.hasColorInputTarget) {
            console.warn('TimeTracker targets nicht gefunden');
            return;
        }

        const timeFromInput = this.timeFromTarget;
        const timeToInput = this.timeToTarget;
        const colorInput = this.colorInputTarget;

        let shiftPresets = localStorage.getItem('shiftPresets');
        shiftPresets = shiftPresets ? JSON.parse(shiftPresets) : {};

        // Prüfe ob shiftPresets.member existiert und ein Array ist
        const shiftPresetsArray = shiftPresets?.member && Array.isArray(shiftPresets.member) ? shiftPresets.member : [];

        let selectedShiftPresetName = selectEl.value;

        // Wenn "Schicht..." oder leer ausgewählt, nichts tun
        if (!selectedShiftPresetName || selectedShiftPresetName === 'Schicht...') {
            return;
        }

        console.log(shiftPresetsArray)
        //sucheim shiftsPresets nach name
        let selectedShiftPreset = shiftPresetsArray.find(preset => preset.name === selectedShiftPresetName);

        if (selectedShiftPreset) {
            // Funktion, um verschiedene Zeitformate zu HH:MM zu konvertieren
            const formatTime = (timeValue) => {
                if (!timeValue) return '';

                // Wenn bereits im Format HH:MM, direkt zurückgeben
                if (typeof timeValue === 'string' && /^\d{2}:\d{2}$/.test(timeValue)) {
                    return timeValue;
                }

                // Wenn ISO-String, parsen
                try {
                    const date = new Date(timeValue);
                    if (!isNaN(date.getTime())) {
                        const hours = date.getHours().toString().padStart(2, '0');
                        const minutes = date.getMinutes().toString().padStart(2, '0');
                        return `${hours}:${minutes}`;
                    }
                } catch (e) {
                    console.warn('Fehler beim Parsen der Zeit:', timeValue, e);
                }

                return '';
            };

            const timeFrom = formatTime(selectedShiftPreset.timeFrom);
            const timeTo = formatTime(selectedShiftPreset.timeTo);
            const color = selectedShiftPreset.color || '#3b82f6';

            // Setze Werte
            if (timeFrom) {
                timeFromInput.value = timeFrom;
            }
            if (timeTo) {
                timeToInput.value = timeTo;
            }
            if (color) {
                colorInput.value = color;
                // Aktualisiere Select-Hintergrundfarbe
                if (this.hasShiftSelectTarget) {
                    this.shiftSelectTarget.style.backgroundColor = color;
                }
                // Aktualisiere auch die Color-Preview (Icon-Box)
                this.updateColor(color);
            }
        } else {
            console.warn('Shift Preset nicht gefunden:', selectedShiftPresetName);
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
