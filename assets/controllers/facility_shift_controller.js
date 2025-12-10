import BaseEntityController from "./base_entity_controller.js";
import FacilityShiftComponentApi from "../api/facility_shift/FacilityShiftComponentApi.js";
import Formater from "../api/helper/Formater.js";
import ApiDataHandler from "../api/ApiDataHandler/ApiDataHandler.js";

export default class extends BaseEntityController {

    static targets = BaseEntityController.targets.concat(["cityInput", "weekDisplay", "facilityList", "facilityButton", "demandShiftsContainer"]);
    static values = {
        currentWeekStart: String,
        dateFrom: String,
        dateTo: String,
        selectedFacilityId: Number,
        facilities: Array
    }

    connect() {
        this.loadAllFacilities();

        this.buildFacilityList().then(() => {
            //wenn die liste geladen hat, kann man die schichten des ersten gebäudes laden
            //get first facilityButtonTarget
            let firstFacilityButton = this.facilityButtonTargets[0];

            //get facility id data
            let facilityId = firstFacilityButton.dataset.facilityId;

            this.selectedFacilityIdValue = facilityId;

            // Format KW 12 • 20.03. - 26.03.
            let currentWeek = this.getMondayOfCurrentWeek();
            this.currentWeekStartValue = currentWeek;

            //set current date from
            this.dateFromValue = currentWeek;
            this.dateToValue = this.getWeekEnd(currentWeek);

            this.loadShiftsOfFacility(facilityId).then(() => {

            })
        });
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

        if(formattedData.name === "Bereitschaft") {
            formattedData.isOnCall = true;
        }

        // amountEmployees zu integer konvertieren
        formattedData.amountEmployees = parseInt(formattedData.amountEmployees);

        // requiredPositions als Array hinzufügen
        formattedData.requiredPositions = requiredPositions;

        formattedData.validFrom = this.dateFromValue
        formattedData.validTo = this.dateToValue

        // Die aktuelle Card speichern um sie später zu ersetzen
        // GEÄNDERT: Von closest("tbody") zu closest("[data-controller='facility-shift-behavior']")
        let currentCard = form.closest("[data-controller='facility-shift-behavior']")

        // Prüfe, ob es ein Update (PUT) oder Create (POST) ist
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

    update(event) {
        // get the form attribute from the event target
        let formId = event.target.attributes["form"].value;

        let form = document.getElementById(formId);

        //trigger submit event on the form
        form.requestSubmit();
    }

    previousWeek() {
        const date = new Date(this.currentWeekStartValue);
        date.setDate(date.getDate() - 7);
        this.currentWeekStartValue = this.formatDate(date);
        this.dateFromValue = this.currentWeekStartValue;
        this.dateToValue = this.getWeekEnd(this.currentWeekStartValue);
        this.loadShiftsOfFacility(this.selectedFacilityIdValue);
    }

    nextWeek() {
        const date = new Date(this.currentWeekStartValue);
        date.setDate(date.getDate() + 7);
        this.currentWeekStartValue = this.formatDate(date);
        this.dateFromValue = this.currentWeekStartValue;
        this.dateToValue = this.getWeekEnd(this.currentWeekStartValue);
        this.loadShiftsOfFacility(this.selectedFacilityIdValue);

    }


    getMondayOfCurrentWeek() {
        const today = new Date();
        const day = today.getDay();
        const diff = today.getDate() - day + (day === 0 ? -6 : 1);
        return this.formatDate(new Date(today.setDate(diff)));
    }

    getWeekEnd(weekStart) {
        const date = new Date(weekStart);
        date.setDate(date.getDate() + 6);
        return this.formatDate(date);
    }

    formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    updateWeekDisplay() {
        if (!this.hasWeekDisplayTarget) return;

        const start = new Date(this.currentWeekStartValue);
        const end = this.getWeekEnd(this.currentWeekStartValue);
        const endDate = new Date(end);

        const weekNumber = this.getWeekNumber(start);
        const startFormatted = `${String(start.getDate()).padStart(2, '0')}.${String(start.getMonth() + 1).padStart(2, '0')}.`;
        const endFormatted = `${String(endDate.getDate()).padStart(2, '0')}.${String(endDate.getMonth() + 1).padStart(2, '0')}.${endDate.getFullYear()}`;

        this.weekDisplayTarget.textContent = `KW ${weekNumber} • ${startFormatted} - ${endFormatted}`;

        //set dateFrom and dateTo values
        this.dateFromValue = this.currentWeekStartValue;
        this.dateToValue = end;

    }

    getWeekNumber(date) {
        const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        const dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }


    loadAllFacilities() {
        ApiDataHandler.getCollection('facilities').then(facilities => {
            // to storage
            this.facilities = facilities;
        })
    }

    buildFacilityList() {
        return FacilityShiftComponentApi.getFacilityListComponent().then(html => {
            this.facilityListTarget.innerHTML = html;
        });
    }

    loadShiftsOfFacility(facilityId) {

        return FacilityShiftComponentApi.getDemandShiftsComponent(this.dateFromValue, this.dateToValue, facilityId).then(html => {
            this.demandShiftsContainerTarget.innerHTML = html;
            this.updateWeekDisplay()
        })
    }

    selectFacility(event) {
        let facilityId = event.currentTarget.dataset.facilityId;
        const clickedButton = event.currentTarget;
        this.selectedFacilityIdValue = facilityId;

        this.loadShiftsOfFacility(facilityId).then(() => {
            //remove all classes activeFacility from all facility buttons
            this.facilityButtonTargets.forEach(button => {
                button.classList.remove('activeFacility');
            });

            //add class activeFacility to the clicked button
            clickedButton.classList.add('activeFacility'); // Gespeicherte Variable nutzen

        });

    }
}
