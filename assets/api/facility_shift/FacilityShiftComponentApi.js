export default class FacilityShiftComponentApi {

    static getNewFacilityShiftComponent(facilityUri) {

        return fetch(`/components/facility-shift/new-shift-component?facilityUri=`+facilityUri, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

    static getFacilityShiftComponent(shiftJsonData) {

        return fetch(`/components/facility-shift/fetch-shift-component`, {
            method: "POST",
            body: JSON.stringify(shiftJsonData),
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

    static getFacilityListComponent() {
        return fetch(`/components/facility-shift/fetch-facility-list-component`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }

    static getDemandShiftsComponent(currentDateFromValue, currentDateToValue, facilityId) {
        return fetch(`/components/facility-shift/fetch-demand-shifts-container-component?facilityId=`+facilityId+`&dateFrom=`+currentDateFromValue+`&dateTo=`+currentDateToValue, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text())
    }
}
