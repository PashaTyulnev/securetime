import BaseEntityController from "./base_entity_controller.js";
import EmployeeComponentApi from "../api/employee/EmployeeComponentApi.js";
import ApiDataHandler from "../api/ApiDataHandler/ApiDataHandler.js";

export default class extends BaseEntityController {

    static targets = BaseEntityController.targets.concat(["firstName", "lastName","userName"]);

    getEntityName() {
        return 'employees';
    }

    getComponentApi() {
        return EmployeeComponentApi;
    }

    getDeleteConfirmMessage() {
        return 'Möchten Sie diesen Mitarbeiter wirklich löschen?';
    }

    updateUsername(event) {
        const firstName = this.firstNameTarget.value.trim();
        const lastName = this.lastNameTarget.value.trim();

        if (firstName && lastName) {
            const username = (firstName[0] + lastName).toLowerCase();
            this.userNameTarget.value = username;
            this.userNameTarget.readOnly = true;
        } else {
            this.userNameTarget.readOnly = false;
        }
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

    sendPasswordEmail(event){

        let emailParam = event.params.email

        ApiDataHandler.postRequest(`/reset-password`, {email: emailParam})
        .then(response => {
            if (response.ok) {
                alert('Passwort-E-Mail wurde gesendet.')
            } else {
                alert('Fehler beim Senden der Passwort-E-Mail: ' + response.data.message)
            }
        })
        .catch(error => {
            alert('Fehler beim Senden der Passwort-E-Mail: ' + error)
        })
    }
}
