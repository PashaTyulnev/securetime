export default class EmployeeComponentApi {
    static getAllEmployeesList() {

        return fetch(`/components/employee/all`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

    static getNewEmployeeModal() {

        return fetch(`/components/employee/new`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }
}
