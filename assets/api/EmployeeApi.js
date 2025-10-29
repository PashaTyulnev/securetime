export default class EmployeeApi {
    static getAllEmployees(contactIds, filterJson = null) {

        //aus contactIds ein string machen der [int,int] diese Form hat
        let contactIdsString = JSON.stringify(contactIds)

        return fetch(`/api/employee/all`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.json())
    }
}
