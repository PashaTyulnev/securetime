import BaseEntityController from "./base_entity_controller.js";
import FacilityShiftComponentApi from "../api/facility_shift/FacilityShiftComponentApi.js";
import Formater from "../api/helper/Formater.js";
import ApiDataHandler from "../api/ApiDataHandler/ApiDataHandler.js";

export default class extends BaseEntityController {

    static targets = BaseEntityController.targets.concat(["cityInput"]);

    connect(){
        this.loadShiftPresets()
    }
    getEntityName() {
        return 'demand_shifts';
    }

    getDeleteConfirmMessage() {
        return 'Möchten Sie diese Schichtvorlage wirklich löschen?';
    }

    createNewShiftElement(event) {
        let facilityId = event.params.facilityId;
        let facilityUri = event.params.facilityUri;
        let shiftsContainer = document.getElementById('facility-shifts-container-' + facilityId)

        FacilityShiftComponentApi.getNewFacilityShiftComponent(facilityUri).then(html => {
            // Eindeutige ID für diese neue Zeile generieren
            const uniqueId = 'new_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

            // Alle Vorkommen von 'new' in IDs durch die eindeutige ID ersetzen
            html = html.replace(/id="([^"]+)_new"/g, `id="$1_${uniqueId}"`);
            html = html.replace(/for="([^"]+)_new"/g, `for="$1_${uniqueId}"`);
            html = html.replace(/shift-form-new/g, `shift-form-${uniqueId}`);
            html = html.replace(/form="shift-form-new"/g, `form="shift-form-${uniqueId}"`);

            shiftsContainer.insertAdjacentHTML('beforeend', html);
        });
    }


    submitShift(event) {
        event.preventDefault();

        let form = event.target;
        let formData = new FormData(form);

        // Alle Wochentage explizit auf true/false setzen
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        days.forEach(day => {
            const fieldName = 'on' + day;
            if (!formData.has(fieldName)) {
                formData.set(fieldName, false);
            } else {
                formData.set(fieldName, true);
            }
        });

        const requiredPositions = formData.getAll('requiredPositions[]');
        formData.delete('requiredPositions[]');

        let formattedData = Formater.formatToJson(formData);

        // amountEmployees zu integer konvertieren
        formattedData.amountEmployees = parseInt(formattedData.amountEmployees);

        // requiredPositions als Array hinzufügen
        formattedData.requiredPositions = requiredPositions;

        // Die aktuelle Card speichern um sie später zu ersetzen
        // GEÄNDERT: Von closest("tbody") zu closest("[data-controller='facility-shift-behavior']")
        let currentCard = form.closest("[data-controller='facility-shift-behavior']")

        // Prüfe ob es ein Update (PUT) oder Create (POST) ist
        const shiftUri = formData.get('uri');

        if (shiftUri) {
            // UPDATE: Bestehende Schicht aktualisieren (PUT)
            ApiDataHandler.updateEntity(this.getEntityName(), formattedData, shiftUri)
                .then(updatedShiftData => {
                    FacilityShiftComponentApi.getFacilityShiftComponent(updatedShiftData).then(html => {
                        currentCard.outerHTML = html;
                    });
                })
                .catch(error => {
                    console.error('Fehler beim Aktualisieren der Schicht:', error);
                    alert('Fehler beim Aktualisieren der Schicht. Bitte versuchen Sie es erneut.');
                });
        } else {
            // CREATE: Neue Schicht erstellen (POST)
            ApiDataHandler.createNewEntity(this.getEntityName(), formattedData)
                .then(newShiftData => {
                    console.log(newShiftData)
                    FacilityShiftComponentApi.getFacilityShiftComponent(newShiftData).then(html => {
                        currentCard.outerHTML = html;
                    });
                })
                .catch(error => {
                    console.error('Fehler beim Speichern der Schicht:', error);
                    alert('Fehler beim Speichern der Schift. Bitte versuchen Sie es erneut.');
                });
        }
    }

    update(event){
        // get the form attribute from the event target
        let formId = event.target.attributes["form"].value;

        let form = document.getElementById(formId);

        //trigger submit event on the form
        form.requestSubmit();
    }

    loadShiftPresets() {
        //einfacher get request an die api um die schicht presets zu laden
        fetch('/api/shift_presets')
            .then(response => response.json())
            .then(data => {
                //to storage
                localStorage.setItem('shiftPresets', JSON.stringify(data));
            });
    }
}
