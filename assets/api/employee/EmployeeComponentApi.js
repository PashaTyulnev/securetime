export default class EmployeeComponentApi {

    static getAllEmployeesList() {

        return fetch(`/components/employee/list`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

    static getCreationModal() {

        return fetch(`/components/employee/new`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

    static getEditModal(employeeId) {
        return fetch(`/components/employee/edit/${employeeId}`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }


}
