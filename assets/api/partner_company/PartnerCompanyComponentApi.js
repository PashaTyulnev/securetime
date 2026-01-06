export default class PartnerCompanyComponentApi {

    static getCreationModal() {
        return fetch(`/components/partner-company/new`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

    static getEditModal(entityId) {
        return fetch(`/components/partner-company/edit/${entityId}`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }
}

