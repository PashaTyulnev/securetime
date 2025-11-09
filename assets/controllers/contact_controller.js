import BaseEntityController from "./base_entity_controller.js";
import ContactComponentApi from "../api/contact/ContactComponentApi.js";

export default class extends BaseEntityController {

    getEntityName() {
        return 'contacts';
    }

    getComponentApi() {
        return ContactComponentApi;
    }

    getDeleteConfirmMessage() {
        return 'Möchten Sie diesen Kontakt wirklich löschen?';
    }
}
