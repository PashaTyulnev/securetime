export default class FacilityComponentApi {

    static getCreationModal() {

        return fetch(`/components/facility/new`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

    static getEditModal(entityId) {
        return fetch(`/components/facility/edit/${entityId}`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

}
