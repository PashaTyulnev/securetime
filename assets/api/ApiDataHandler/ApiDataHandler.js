export default class ApiDataHandler {
    /**
     * Erstellt eine neue Entität über die API
     * @param {string} entityType - Der Typ der Entität (z.B. 'companies', 'users', etc.)
     * @param {Object} data - Die zu erstellenden Daten
     * @param extendedInfo
     * @returns {Promise<Object>} - Die API-Antwort
     */
    static async createNewEntity(entityType, data, extendedInfo = false) {
        const response = await fetch(`/api/${entityType}`, {
            method: "POST",
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        });

        let body;
        try {
            body = await response.json();
        } catch {
            body = {};
        }

        if (extendedInfo === false) {
            return body
        } else {
            return {
                status: response.status,
                ok: response.ok,
                data: body
            };
        }
    }


    /**
     * Aktualisiert eine Entität über die API
     * @param {string} entityType - Der Typ der Entität (z.B. 'companies', 'users', etc.)
     * @param {Object} data - Die zu aktualisierenden Daten
     * @param {string|number} entityUri - Die URI der zu aktualisierenden Entität
     * @returns {Promise<Object>} - Die API-Antwort
     */
    static async updateEntity(entityType, data, entityUri) {
        // Cache-Busting: Füge Timestamp zur URI hinzu
        const separator = entityUri.includes('?') ? '&' : '?';
        const urlWithCacheBust = `${entityUri}${separator}_t=${Date.now()}`;

        const response = await fetch(urlWithCacheBust, {
            method: "PUT",
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
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
     * @param {string|number} uri - Die URI der zu löschenden Entität
     * @returns {Promise<Response>} - Die Fetch-Response
     */
    static async deleteEntity(entityType, uri) {
        // Cache-Busting: Füge Timestamp zur URI hinzu
        const separator = uri.includes('?') ? '&' : '?';
        const urlWithCacheBust = `${uri}${separator}_t=${Date.now()}`;

        return await fetch(urlWithCacheBust, {
            method: "DELETE",
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        });
    }
}
