export default class ScheduleComponentApi {

  static getWeekScheduleComponent(year, week) {
      return fetch(`/components/schedule-week?year=${year}&week=${week}`, {
          method: "GET",
          headers: {
              'Content-Type': 'application/json'
          }
      }).then(response => response.text());
  }

    static getMonthScheduleComponent(year, month) {
        return fetch(`/components/schedule-month?year=${year}&month=${month}`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text());
    }


    static getDemandShiftsOfFacilityComponent(facilityId) {
        return fetch(`/components/schedule/demand-shifts?facilityId=${facilityId}`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text());
    }


    static getMiniShiftComponent(newShift) {
        return fetch(`/components/schedule/mini-shift-component`, {
            method: "POST",
            body: JSON.stringify(newShift),
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text());
    }
}
