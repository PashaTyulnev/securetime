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

    toggleStatus(event) {

        let employeeId = event.params.id

        fetch(`/api/employees/toggle-status/${employeeId}`, {
            method: "PUT",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => {
            if (response.ok) {
                window.location.reload()
            }
        }).catch(error => {
            alert(error)
        })
    }
}
