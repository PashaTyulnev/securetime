export default class ContactComponentApi {

    static getCreationModal() {

        return fetch(`/components/contact/new`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

    static getEditModal(entityId) {
        return fetch(`/components/contact/edit/${entityId}`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

}
