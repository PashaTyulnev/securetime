import { Controller } from '@hotwired/stimulus';
import EmployeeComponentApi from "../api/EmployeeComponentApi.js";

export default class extends Controller {

    static targets = [ "employeeList" ]
    connect() {
        EmployeeComponentApi.getAllEmployeesList().then(html => {
            this.employeeListTarget.innerHTML = html;
        })

    }
}
