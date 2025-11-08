import BaseEntityController from "./base_entity_controller.js";
import EmployeeComponentApi from "../api/employee/EmployeeComponentApi.js";

export default class extends BaseEntityController {

    getEntityName() {
        return 'employees';
    }

    getComponentApi() {
        return EmployeeComponentApi;
    }

    getDeleteConfirmMessage() {
        return 'Möchten Sie diesen Mitarbeiter wirklich löschen?';
    }
}
