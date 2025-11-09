export default class ApiDataHandler {
    /**
     * Erstellt eine neue Entität über die API
     * @param {string} entityType - Der Typ der Entität (z.B. 'companies', 'users', etc.)
     * @param {Object} data - Die zu erstellenden Daten
     * @returns {Promise<Object>} - Die API-Antwort
     */
    static async createNewEntity(entityType, data) {
        const response = await fetch(`/api/${entityType}`, {
            method: "POST",
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (response.ok) {
            // Erfolgreich → API Platform gibt das neue Objekt zurück
            return await response.json();
        } else {
            // Fehler → JSON von API Platform lesen
            let errorData;
            try {
                errorData = await response.json();
            } catch {
                errorData = {};
            }
            return errorData;
        }
    }

    /**
     * Aktualisiert eine Entität über die API
     * @param {string} entityType - Der Typ der Entität (z.B. 'companies', 'users', etc.)
     * @param {Object} data - Die zu aktualisierenden Daten
     * @param {string|number} entityUri - Die ID der zu aktualisierenden Entität
     * @returns {Promise<Object>} - Die API-Antwort
     */
    static async updateEntity(entityType, data, entityUri) {
        const response = await fetch(entityUri, {
            method: "PUT",
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (response.ok) {
            // Erfolgreich → API Platform gibt das aktualisierte Objekt zurück
            return await response.json();
        } else {
            // Fehler → JSON von API Platform lesen
            let errorData;
            try {
                errorData = await response.json();
            } catch {
                errorData = {};
            }
            return errorData;
        }
    }

    /**
     * Löscht eine Entität über die API
     * @param {string} entityType - Der Typ der Entität (z.B. 'companies', 'users', etc.)
     * @param {string|number} uri - Die ID der zu löschenden Entität
     * @returns {Promise<Response>} - Die Fetch-Response
     */
    static async deleteEntity(entityType, uri) {
        return await fetch(uri, {
            method: "DELETE",
            headers: {
                'Content-Type': 'application/json'
            }
        });
    }
}
