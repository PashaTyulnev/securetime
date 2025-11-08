export default class CompanyDataApi {

    static async createNewCompany(data) {
        const response = await fetch(`/api/companies`, {
            method: "POST",
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (response.ok) {
            // Erfolgreich → API Platform gibt das neue Company-Objekt zurück
            // Bei 201 oder 200
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

    static async deleteEntity(id) {
        return await fetch(`/api/companies/${id}`, {
            method: "DELETE",
            headers: {
                'Content-Type': 'application/json'
            }
        }); // delete handhaben wir direkt im Stimulus-Controller
    }
}
