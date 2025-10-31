import {Controller} from '@hotwired/stimulus';
import EmployeeComponentApi from "../api/EmployeeComponentApi.js";
import EmployeeDataApi from "../api/EmployeeDataApi.js";

export default class extends Controller {

    static targets = ["employeeListContainer", "employeeCreationModalContainer", "errorMessageContainer", "errorMessage"]

    connect() {
        EmployeeComponentApi.getAllEmployeesList().then(html => {
            this.employeeListContainerTarget.innerHTML = html;
        })
    }

    createNew(event) {
        event.preventDefault();

        // Fehlercontainer zurücksetzen
        this.errorMessageContainerTarget.removeAttribute('hidden');
        this.errorMessageTarget.textContent = ''; // alten Text entfernen

        let formData = new FormData(event.target);
        let jsonData = Object.fromEntries(formData.entries());

        EmployeeDataApi.createNewEmployee(jsonData)
            .then(response => {
                // Erfolgsfall - Status 201 "Created"
                if (response && response.status === 'success') {
                    // Seite neu laden
                    window.location.reload();
                    return;
                }

                //remove class "hidden" and "opacity-0" from errorMessageContainerTarget to show the error message
                this.errorMessageContainerTarget.classList.remove('hidden', 'opacity-0');

                this.errorMessageTarget.textContent = response.message || 'Ein unbekannter Fehler ist aufgetreten';
                this.errorMessageContainerTarget.removeAttribute('hidden');

            })
            .catch(err => {
                this.errorMessageTarget.textContent = 'Ein Netzwerkfehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
                this.errorMessageContainerTarget.removeAttribute('hidden');
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

        fetch(`/api/employee/delete/${employeeId}`, {
            method: "DELETE",
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
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


}
