export default class CompanyComponentApi {

    static getCreationModal() {

        return fetch(`/components/company/new`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

}
