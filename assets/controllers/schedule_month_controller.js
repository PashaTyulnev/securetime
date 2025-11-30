import {Controller} from "@hotwired/stimulus";
import DateHelper from "../helper/date_helper.js";
import ScheduleComponentApi from "../api/schedule/ScheduleComponentApi.js";
import ApiDataHandler from "../api/ApiDataHandler/ApiDataHandler.js";

export default class extends Controller {
    static targets = [
        "currentMonthText",
        "currentYearText",
        "currentMonthInput",
        "scheduleContainer",
        "facilityShiftsContainer",
        "facilitySelect",
        "demandShiftsContainer",
        "miniShiftPill"
    ];

    connect() {
        this.currentDate = new Date();
        this.renderMonth();
    }

    previousMonth() {
        this.currentDate.setMonth(this.currentDate.getMonth() - 1);
        this.renderMonth();
    }

    nextMonth() {
        this.currentDate.setMonth(this.currentDate.getMonth() + 1);
        this.renderMonth();
    }

    today() {
        this.currentDate = new Date();
        this.renderMonth();
    }

    renderMonth() {
        const monthNames = ["Januar", "Februar", "März", "April", "Mai", "Juni",
            "Juli", "August", "September", "Oktober", "November", "Dezember"];

        this.currentMonthTextTarget.textContent = monthNames[this.currentDate.getMonth()];
        this.currentYearTextTarget.textContent = this.currentDate.getFullYear();
        this.currentMonthInputTarget.value = this.currentDate.getMonth() + 1;


         this.buildScheduleElement()

    }

    buildScheduleElement() {
        let month = this.currentDate.getMonth() + 1; // Monate sind 0-basiert
        let year = this.currentDate.getFullYear();

        // WICHTIG: Promise zurückgeben!
        return ScheduleComponentApi.getMonthScheduleComponent(year, month).then(demandShiftsComponent => {
            this.scheduleContainerTarget.innerHTML = demandShiftsComponent;
        });
    }
}
