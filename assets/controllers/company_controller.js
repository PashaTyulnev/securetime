import BaseEntityController from "./base_entity_controller.js";
import CompanyComponentApi from "../api/company/CompanyComponentApi.js";

export default class extends BaseEntityController {

    getEntityName() {
        return 'companies';
    }

    getComponentApi() {
        return CompanyComponentApi;
    }

    getDeleteConfirmMessage() {
        return 'Möchten Sie diese Firma wirklich löschen?';
    }
}
