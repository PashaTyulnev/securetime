export default class ScheduleComponentApi {

  static getWeekScheduleComponent(year, week) {
      return fetch(`/components/schedule-week?year=${year}&week=${week}`, {
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



}
