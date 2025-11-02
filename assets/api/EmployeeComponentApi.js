export default class EmployeeComponentApi {

    static getAllEmployeesList() {

        return fetch(`/components/employee/all`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

    static getEmployeeCreationModal() {

        return fetch(`/components/employee/new`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

    static getEmployeeEditModal(employeeId) {
        return fetch(`/components/employee/edit/${employeeId}`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }
}
