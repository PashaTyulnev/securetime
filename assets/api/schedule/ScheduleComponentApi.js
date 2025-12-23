export default class ScheduleComponentApi {

  static getWeekScheduleComponent(year, week, facilityId=null, weekSpan = 1) {
      return fetch(`/components/schedule-week?year=${year}&week=${week}&weekSpan=${weekSpan}&facilityId=${facilityId}`, {
          method: "GET",
          headers: {
              'Content-Type': 'application/json'
          }
      }).then(response => response.text());
  }

    static getTwoWeekScheduleComponent(year, week, facilityId = null) {
        return fetch(`/components/schedule-two-week?year=${year}&week=${week}&facilityId=${facilityId}`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text());
    }

    static getMonthScheduleComponent(year, month, facilityId = null) {
        return fetch(`/components/schedule-month?year=${year}&month=${month}&facilityId=${facilityId}`, {
            method: "GET",
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(response => response.text());
    }


    static getDemandShiftsOfFacilityComponent(facilityId,dateFrom,dateTo) {
        return fetch(`/components/schedule/demand-shifts?facilityId=${facilityId}&dateFrom=${dateFrom}&dateTo=${dateTo}`, {
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
