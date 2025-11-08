import {Controller} from '@hotwired/stimulus';

import EmployeeComponentApi from "../api/employee/EmployeeComponentApi.js";
import EmployeeDataApi from "../api/employee/EmployeeDataApi.js";

export default class extends Controller {

    static targets = ["employeeListContainer",
        "employeeFormModal", "employeeModalContainer", "errorMessageContainer", "errorMessage"]

    connect() {
        EmployeeComponentApi.getAllEmployeesList().then(html => {
            this.employeeListContainerTarget.innerHTML = html;
        })
    }


    openEmployeeCreationModal(event) {
        EmployeeComponentApi.getEmployeeCreationModal().then(html => {
            this.employeeModalContainerTarget.innerHTML = html;
        });
    }

    openEmployeeEditModal(event) {
        event.preventDefault();
        const employeeId = event.params.id;

        EmployeeComponentApi.getEmployeeEditModal(employeeId).then(html => {
            this.employeeModalContainerTarget.innerHTML = html;
        });
    }

    saveNew(event) {
        event.preventDefault();
        this.handleSave(event, (data) => EmployeeDataApi.createNewEmployee(data));
    }

    saveEdit(event) {
        event.preventDefault();
        this.handleSave(event, (data) => EmployeeDataApi.updateEmployee(data));
    }

    handleSave(event, apiCall) {
        // Fehlercontainer zurücksetzen
        this.errorMessageTarget.textContent = '';
        this.errorMessageContainerTarget.classList.add('hidden');

        // Formulardaten als JSON
        const formData = new FormData(event.target);
        const jsonData = Object.fromEntries(formData.entries());

        // API-Aufruf (Promise)
        apiCall(jsonData)
            .then(response => {
                if (response && response.status === 'success') {
                    // Erfolgreich -> Seite neu laden
                    window.location.reload();
                    return;
                }

                // Fehler anzeigen
                this.showError(response.message || 'Ein unbekannter Fehler ist aufgetreten');
            })
            .catch(() => {
                this.showError('Ein Netzwerkfehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
            });
    }

    deleteEmployee(event) {
        event.preventDefault();
        const employeeId = event.params.id;

        // Abfrage, ob der Benutzer wirklich löschen möchte
        const confirmed = confirm("Möchten Sie diesen Mitarbeiter wirklich löschen?");
        if (!confirmed) {
            return; // Abbrechen, wenn der Benutzer auf "Abbrechen" klickt
        }

        EmployeeDataApi.deleteEmployee(employeeId).then(data => {
            if (data && data.status === 'success') {
                window.location.reload();
            } else {
                alert(data.message || 'Ein unbekannter Fehler ist aufgetreten');
            }
        })
            .catch(err => {
                alert('Ein Netzwerkfehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
            });
    }

    showError(message) {
        this.errorMessageTarget.textContent = message;
        this.errorMessageContainerTarget.classList.remove('hidden', 'opacity-0');
        this.errorMessageContainerTarget.removeAttribute('hidden');
    }



}
