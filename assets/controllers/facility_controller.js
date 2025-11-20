import BaseEntityController from "./base_entity_controller.js";
import FacilityComponentApi from "../api/facility/FacilityComponentApi.js";
import ApiDataHandler from "../api/ApiDataHandler/ApiDataHandler.js";
import Formater from "../api/helper/Formater.js";

export default class extends BaseEntityController {

    static targets = BaseEntityController.targets.concat(["cityInput", "positionsForm"]);

    getEntityName() {
        return 'facilities';
    }

    getComponentApi() {
        return FacilityComponentApi;
    }

    getDeleteConfirmMessage() {
        return 'Möchten Sie dieses Objekt wirklich löschen?';
    }

    saveNew(event) {
        event.preventDefault();

        // Fehlercontainer zurücksetzen
        this.errorMessageTarget.textContent = '';
        this.errorMessageContainerTarget.classList.add('hidden');

        const formData = new FormData(event.target);
        const jsonData = Formater.formatToJson(formData);

        // 1. Facility erstellen
        ApiDataHandler.createNewEntity(this.getEntityName(), jsonData)
            .then(async (response) => {
                if (!response || Object.keys(response).length === 0) {
                    this.showError('Keine Antwort vom Server.');
                    return;
                }

                if (response.status >= 400) {
                    const message = response.description || 'Ein unbekannter Fehler ist aufgetreten.';
                    this.showError(message);
                    return;
                }

                const newFacilityUri = response['@id'];

                // 2. Positionen erstellen
                await this.createPositions(newFacilityUri);

                // 3. Modal schließen und Seite neu laden
                this.closeModal();
                window.location.reload();
            })
            .catch((error) => {
                console.error('Fehler beim Erstellen der Facility:', error);
                this.showError('Netzwerkfehler oder Serverfehler.');
            });
    }

    saveEdit(event) {
        event.preventDefault();
        const facilityUri = event.params.uri;

        // Fehlercontainer zurücksetzen
        this.errorMessageTarget.textContent = '';
        this.errorMessageContainerTarget.classList.add('hidden');

        const formData = new FormData(event.target);
        const jsonData = Formater.formatToJson(formData);

        // 1. Facility aktualisieren
        ApiDataHandler.updateEntity(this.getEntityName(), jsonData, facilityUri)
            .then(async (response) => {
                if (!response || Object.keys(response).length === 0) {
                    this.showError('Keine Antwort vom Server.');
                    return;
                }

                if (response.status >= 400) {
                    const message = response.description || 'Ein unbekannter Fehler ist aufgetreten.';
                    this.showError(message);
                    return;
                }

                // 2. Positionen aktualisieren (alte löschen, neue erstellen)
                await this.createPositions(facilityUri);

                // 3. Modal schließen und Seite neu laden
                this.closeModal();
                window.location.reload();
            })
            .catch((error) => {
                console.error('Fehler beim Aktualisieren der Facility:', error);
                this.showError('Netzwerkfehler oder Serverfehler.');
            });
    }

    async createPositions(facilityUri) {
        const positionController = this.application.getControllerForElementAndIdentifier(
            this.positionsFormTarget,
            'facility-position'
        );

        if (!positionController) {
            console.warn('Position Controller nicht gefunden');
            return;
        }

        const positions = positionController.getPositionsData();

        if (positions.length === 0) {
            return; // Keine Positionen zu erstellen
        }

        // Alle Positionen parallel erstellen
        const positionPromises = positions.map(position => {
            const positionData = {
                title: position.title,
                shortName: position.shortName,
                note: position.note,
                facility: facilityUri
            };

            return ApiDataHandler.createNewEntity('facility_positions', positionData);
        });

        try {
            await Promise.all(positionPromises);
        } catch (error) {
            console.error('Fehler beim Erstellen der Positionen:', error);
            throw error;
        }
    }

    closeModal() {
        // Modal-Controller finden und hide aufrufen
        const modalElement = this.element.closest('[data-controller*="modal"]');
        if (modalElement) {
            const modalController = this.application.getControllerForElementAndIdentifier(
                modalElement,
                'modal'
            );
            if (modalController && typeof modalController.hide === 'function') {
                modalController.hide();
            }
        }
    }


    async fetchCityByZip(event) {
        const input = event.target;
        input.value = input.value.replace(/\D/g, '');

        const zip = input.value.trim();
        const cityInput = this.cityInputTarget;

        if (!/^\d{5}$/.test(zip)) {
            cityInput.value = '';
            return;
        }

        try {
            const response = await fetch(`https://api.zippopotam.us/de/${zip}`);

            if (!response.ok) {
                cityInput.value = '';
                return;
            }

            const data = await response.json();
            const place = data.places?.[0];
            cityInput.value = place ? place['place name'] : '';

        } catch (error) {
            console.error('Fehler beim Laden der Stadt:', error);
            cityInput.value = '';
        }
    }
}
