export default class EmployeeDataApi {
    static createNewEmployee(data) {

        return fetch(`/api/employee/create`, {
            method: "POST",
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.json())
    }

    static deleteEmployee(employeeId) {
        return fetch(`/api/employee/delete/${employeeId}`, {
            method: "DELETE",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.json())

    }

    static updateEmployee(data) {

        return fetch(`/api/employee/update/${data.employeeId}`, {
            method: "PUT",
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.json())

    }
}
