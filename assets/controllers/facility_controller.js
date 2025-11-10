import BaseEntityController from "./base_entity_controller.js";
import FacilityComponentApi from "../api/facility/FacilityComponentApi.js";

export default class extends BaseEntityController {

    static targets = BaseEntityController.targets.concat(["cityInput"]);

    getEntityName() {
        return 'facilities';
    }

    getComponentApi() {
        return FacilityComponentApi;
    }

    getDeleteConfirmMessage() {
        return 'Möchten Sie dieses Objekt wirklich löschen?';
    }

    async fetchCityByZip(event) {
        const input = event.target;
        input.value = input.value.replace(/\D/g, ''); // Buchstaben entfernen

        const zip = input.value.trim();
        const cityInput = this.cityInputTarget;

        // Nur gültige 5-stellige deutsche PLZ akzeptieren
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
