import BaseEntityController from "./base_entity_controller.js";
import FacilityComponentApi from "../api/facility/FacilityComponentApi.js";

export default class extends BaseEntityController {

    getEntityName() {
        return 'facilities';
    }

    getComponentApi() {
        return FacilityComponentApi;
    }

    getDeleteConfirmMessage() {
        return 'Möchten Sie dieses Objekt wirklich löschen?';
    }
}
