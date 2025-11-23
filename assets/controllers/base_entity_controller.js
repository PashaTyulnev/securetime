import {Controller} from '@hotwired/stimulus';
import ApiDataHandler from "../api/ApiDataHandler/ApiDataHandler.js";
import Formater from "../api/helper/Formater.js";

/**
 * Basis-Controller für Entity-Management (CRUD-Operationen)
 *
 * Diese Klasse stellt gemeinsame Funktionalität für Employee, Company und andere Entities bereit.
 *
 * Erforderliche statische Properties in abgeleiteten Klassen:
 * - entityName: String (z.B. 'employees', 'companies')
 * - componentApi: Klasse mit getCreationModal() und getEditModal(id)
 * - deleteConfirmMessage: String (z.B. 'Möchten Sie diesen Mitarbeiter wirklich löschen?')
 */
export default class extends Controller {

    static targets = ["modalContainer", "errorMessage", "errorMessageContainer"]

    connect() {
        // Kann in abgeleiteten Klassen überschrieben werden
    }

    /**
     * Öffnet das Modal zum Erstellen einer neuen Entity
     */
    openCreationModal(event) {
        this.getComponentApi().getCreationModal().then(html => {
            this.modalContainerTarget.innerHTML = html;
        });
    }

    /**
     * Öffnet das Modal zum Bearbeiten einer Entity
     */
    openEditModal(event) {
        event.preventDefault();
        const entityId = event.params.id;

        this.getComponentApi().getEditModal(entityId).then(html => {
            this.modalContainerTarget.innerHTML = html;
        });
    }

    /**
     * Speichert eine neue Entity
     */
    saveNew(event) {
        event.preventDefault();
        this.handleSave(event, (data) => ApiDataHandler.createNewEntity(this.getEntityName(), data));
    }

    /**
     * Speichert Änderungen an einer bestehenden Entity
     */
    saveEdit(event) {
        event.preventDefault();
        const entityUri = event.params.uri;
        this.handleSave(event, (data) => ApiDataHandler.updateEntity(this.getEntityName(), data, entityUri));
    }

    /**
     * Löscht eine Entity
     */
    delete(event) {
        event.preventDefault();
        const uri = event.params.uri;

        // Abfrage, ob der Benutzer wirklich löschen möchte
        const confirmed = confirm(this.getDeleteConfirmMessage());
        if (!confirmed) {
            return;
        }

        ApiDataHandler.deleteEntity(this.getEntityName(), uri)
            .then(response => {
                if (response.ok) {
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


                        alert(data.description);
                    });
                }
            })
            .catch(() => {
                alert('Netzwerkfehler. Bitte versuchen Sie es später erneut.');
            });
    }

    /**
     * Behandelt das Speichern von Formulardaten
     * @param {Event} event - Das Formular-Event
     * @param {Function} apiCall - Die API-Funktion, die aufgerufen werden soll
     */
    handleSave(event, apiCall) {
        event.preventDefault();

        // Fehlercontainer zurücksetzen
        this.errorMessageTarget.textContent = '';
        this.errorMessageContainerTarget.classList.add('hidden');

        // Formulardaten als JSON (verschachtelte Felder werden unterstützt)
        const formData = new FormData(event.target);
        const jsonData = Formater.formatToJson(formData);

        // API-Aufruf
        apiCall(jsonData)
            .then(response => {

                //check response is empty

                if (!response || Object.keys(response).length === 0) {
                    this.showError('Keine Antwort vom Server.');
                    return;
                }

                if (response.status >= 400) {
                    const message = response.description || 'Ein unbekannter Fehler ist aufgetreten.';
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

    /**
     * Zeigt eine Fehlermeldung an
     */
    showError(message) {
        this.errorMessageTarget.textContent = message;
        this.errorMessageContainerTarget.classList.remove('hidden', 'opacity-0');
        this.errorMessageContainerTarget.removeAttribute('hidden');
    }

    /**
     * Gibt den Entity-Namen zurück (muss in abgeleiteten Klassen überschrieben werden)
     */
    getEntityName() {
        throw new Error('getEntityName() muss in der abgeleiteten Klasse überschrieben werden');
    }

    /**
     * Gibt die Component-API zurück (muss in abgeleiteten Klassen überschrieben werden)
     */
    getComponentApi() {
        throw new Error('getComponentApi() muss in der abgeleiteten Klasse überschrieben werden');
    }

    /**
     * Gibt die Bestätigungsnachricht für das Löschen zurück (muss in abgeleiteten Klassen überschrieben werden)
     */
    getDeleteConfirmMessage() {
        throw new Error('getDeleteConfirmMessage() muss in der abgeleiteten Klasse überschrieben werden');
    }
}

