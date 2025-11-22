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
        this.resetErrors();

        const formData = new FormData(event.target);
        const jsonData = Formater.formatToJson(formData);

        ApiDataHandler.createNewEntity(this.getEntityName(), jsonData)
            .then(response => this.handleSaveResponse(response))
            .catch(error => this.handleSaveError(error, 'Erstellen'));
    }

    saveEdit(event) {
        event.preventDefault();
        const facilityUri = event.params.uri;
        this.resetErrors();

        const formData = new FormData(event.target);
        const jsonData = Formater.formatToJson(formData);

        ApiDataHandler.updateEntity(this.getEntityName(), jsonData, facilityUri)
            .then(response => this.handleSaveResponse(response, facilityUri))
            .catch(error => this.handleSaveError(error, 'Aktualisieren'));
    }

    async handleSaveResponse(response, facilityUri = null) {
        if (!response || Object.keys(response).length === 0) {
            this.showError('Keine Antwort vom Server.');
            return;
        }

        if (response.status >= 400) {
            const message = response.description || 'Ein unbekannter Fehler ist aufgetreten.';
            this.showError(message);
            return;
        }

        // URI ermitteln (bei neuem Entity aus Response, bei Edit aus Parameter)
        const uri = facilityUri || response['@id'];
        console.log("Facility URI:", uri);

        // Positionen erstellen/aktualisieren
        await this.createPositions(uri);

        // Abschließen
        this.closeModal();
        this.reloadList();
    }

    handleSaveError(error, action) {
        console.error(`Fehler beim ${action} der Facility:`, error);
        this.showError('Netzwerkfehler oder Serverfehler.');
    }

    resetErrors() {
        this.errorMessageTarget.textContent = '';
        this.errorMessageContainerTarget.classList.add('hidden');
    }

    async createPositions(facilityUri) {
        const positionController = this.getPositionController();

        if (!positionController) {
            console.warn('Position Controller nicht gefunden');
            return;
        }

        const positions = positionController.getPositionsData();

        if (positions.length === 0) {
            return;
        }

        const positionPromises = positions.map(position =>
            this.createSinglePosition(position, facilityUri)
        );

        try {
            await Promise.all(positionPromises);
        } catch (error) {
            console.error('Fehler beim Erstellen der Positionen:', error);
            throw error;
        }
    }

    createSinglePosition(position, facilityUri) {
        const positionData = {
            title: position.title,
            shortName: position.shortName,
            note: position.note,
            facility: facilityUri
        };

        return ApiDataHandler.createNewEntity('facility_positions', positionData);
    }

    getPositionController() {
        return this.application.getControllerForElementAndIdentifier(
            this.positionsFormTarget,
            'facility-position'
        );
    }

    closeModal() {
        const modalElement = this.modalContainerTarget.querySelector('[data-controller="modal"]');

        if (!modalElement) {
            console.warn('Modal-Element nicht gefunden');
            return;
        }

        const modalController = this.application.getControllerForElementAndIdentifier(
            modalElement,
            'modal'
        );

        if (modalController) {
            modalController.hide();
        } else {
            console.warn('Modal-Controller nicht gefunden');
        }
    }

    reloadList() {
        const turboFrame = document.getElementById('facilities-table');

        if (turboFrame) {
            turboFrame.reload();
        } else {
            console.warn('Turbo Frame "facilities-table" nicht gefunden');
        }
    }

    async fetchCityByZip(event) {
        const input = event.target;
        input.value = input.value.replace(/\D/g, '');

        const zip = input.value.trim();
        const cityInput = this.cityInputTarget;

        if (!this.isValidGermanZip(zip)) {
            cityInput.value = '';
            return;
        }

        try {
            const cityName = await this.getCityByZip(zip);
            cityInput.value = cityName;
        } catch (error) {
            console.error('Fehler beim Laden der Stadt:', error);
            cityInput.value = '';
        }
    }

    isValidGermanZip(zip) {
        return /^\d{5}$/.test(zip);
    }

    async getCityByZip(zip) {
        const response = await fetch(`https://api.zippopotam.us/de/${zip}`);

        if (!response.ok) {
            return '';
        }

        const data = await response.json();
        const place = data.places?.[0];

        return place ? place['place name'] : '';
    }
}
