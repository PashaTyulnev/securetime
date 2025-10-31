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

}
