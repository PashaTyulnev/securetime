import BaseEntityController from "./base_entity_controller.js";
import PartnerCompanyComponentApi from "../api/partner_company/PartnerCompanyComponentApi.js";

export default class extends BaseEntityController {

    getEntityName() {
        return 'partner_companies';
    }

    getComponentApi() {
        return PartnerCompanyComponentApi;
    }

    getDeleteConfirmMessage() {
        return 'Möchten Sie diesen Geschäftspartner wirklich löschen?';
    }
}

