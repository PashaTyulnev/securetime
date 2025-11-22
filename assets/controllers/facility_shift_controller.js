import BaseEntityController from "./base_entity_controller.js";
import FacilityShiftComponentApi from "../api/facility_shift/FacilityShiftComponentApi.js";
import Formater from "../api/helper/Formater.js";
import ApiDataHandler from "../api/ApiDataHandler/ApiDataHandler.js";

export default class extends BaseEntityController {

    static targets = BaseEntityController.targets.concat(["cityInput"]);

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


    submitNewShift(event) {
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

        let formattedData = Formater.formatToJson(formData);

        //konvertiere amountEmployees zu integer
        formattedData.amountEmployees = parseInt(formattedData.amountEmployees);

        // Die aktuelle Zeile speichern um sie später zu ersetzen
        let currentRow = form.closest('tr');

        // Prüfe ob es ein Update (PUT) oder Create (POST) ist
        const shiftUri = formData.get('uri');

        if (shiftUri) {
            // UPDATE: Bestehende Schicht aktualisieren (PUT)
            ApiDataHandler.updateEntity(this.getEntityName(), formattedData, shiftUri)
                .then(updatedShiftData => {
                    FacilityShiftComponentApi.getFacilityShiftComponent(updatedShiftData).then(html => {
                        currentRow.outerHTML = html;
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
                    FacilityShiftComponentApi.getFacilityShiftComponent(newShiftData).then(html => {
                        currentRow.outerHTML = html;
                    });
                })
                .catch(error => {
                    console.error('Fehler beim Speichern der Schicht:', error);
                    alert('Fehler beim Speichern der Schicht. Bitte versuchen Sie es erneut.');
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
}
