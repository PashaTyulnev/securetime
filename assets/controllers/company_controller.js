import {Controller} from '@hotwired/stimulus';

import CompanyComponentApi from "../api/company/CompanyComponentApi.js";
import CompanyDataApi from "../api/company/CompanyDataApi.js";
import Formater from "../api/helper/Formater.js";



export default class extends Controller {

    static targets = ["modalContainer","errorMessage", "errorMessageContainer"]

    connect() {

    }

    openCreationModal(event) {
        CompanyComponentApi.getCreationModal().then(html => {
            this.modalContainerTarget.innerHTML = html;
        });
    }

    saveNew(event) {
        event.preventDefault();
        this.handleSave(event, (data) => CompanyDataApi.createNewCompany(data));
    }

    delete(event) {
        event.preventDefault();
        const companyId = event.params.id;

        if (!confirm("Möchten Sie diese Firma wirklich löschen?")) {
            return;
        }

        CompanyDataApi.deleteEntity(companyId)
            .then(response => {
                if (response.ok) {
                    // 2xx Status → Seite neu laden, egal ob 204 oder 200
                    window.location.reload();
                } else {
                    // Fehlercodes → versuchen JSON auszulesen
                    response.text().then(text => {
                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch {
                            data = {};
                        }
                        alert(data['hydra:description'] || 'Ein unbekannter Fehler ist aufgetreten');
                    });
                }
            })
            .catch(() => {
                alert('Netzwerkfehler. Bitte versuchen Sie es später erneut.');
            });
    }



    handleSave(event, apiCall) {
        event.preventDefault();

        // Fehlercontainer zurücksetzen
        this.errorMessageTarget.textContent = '';
        this.errorMessageContainerTarget.classList.add('hidden');

        // Formulardaten als JSON (verschachtelte Address-Felder werden unterstützt)
        const formData = new FormData(event.target);
        const jsonData = Formater.formatToJson(formData);

        // API-Aufruf
        apiCall(jsonData)
            .then(response => {
                if (!response) {
                    this.showError('Keine Antwort vom Server.');
                    return;
                }

                if (response['@type'] === 'ConstraintViolationList') {
                    // Validierungsfehler von API Platform
                    const message = Object.values(response['violations'] || {}).map(v => v.message).join(', ');
                    this.showError(message);
                    return;
                }

                // Erfolgreich → Seite neu laden
                window.location.reload();
            })
            .catch(() => {
                this.showError('Netzwerkfehler oder Serverfehler.');
            });
    }


    showError(message) {
        this.errorMessageTarget.textContent = message;
        this.errorMessageContainerTarget.classList.remove('hidden', 'opacity-0');
        this.errorMessageContainerTarget.removeAttribute('hidden');
    }

}
