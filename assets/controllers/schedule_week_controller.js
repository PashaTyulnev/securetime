import {Controller} from "@hotwired/stimulus";
import WeekSwitcher from "../service/week_switcher.js";
import DateHelper from "../helper/date_helper.js";
import ScheduleComponentApi from "../api/schedule/ScheduleComponentApi.js";

export default class extends Controller {
    static targets = ["currentWeekText", "currentDateRangeText", "currentWeekInput", "scheduleContainer"];

    connect() {
        this.currentDate = new Date();
        this.currentWeek = DateHelper.getWeekNumber(this.currentDate);
        this.renderWeek();
    }


    buildScheduleElement() {

        let year = this.currentDate.getFullYear();
        let week = this.currentWeek;

        ScheduleComponentApi.getWeekScheduleComponent(year,week).then(schedule => {
            this.scheduleContainerTarget.innerHTML = schedule;
        })
    }

    /**
     * Wochenswitcher-Funktionen
     */

    renderWeek() {
        this.currentWeekTextTarget.textContent = "Woche " + this.currentWeek;
        this.currentDateRangeTextTarget.textContent = WeekSwitcher.getWeekRangeText(this.currentWeek, this.currentDate.getFullYear());
        this.currentWeekInputTarget.value = this.currentWeek;

        this.buildScheduleElement()
    }

    previousWeek() {
        const result = WeekSwitcher.changeWeek(this.currentWeek, this.currentDate, -1);
        this.currentWeek = result.week;
        this.currentDate = result.date;
        this.renderWeek();
    }

    nextWeek() {
        const result = WeekSwitcher.changeWeek(this.currentWeek, this.currentDate, 1);
        this.currentWeek = result.week;
        this.currentDate = result.date;
        this.renderWeek();
    }

    today() {
        this.currentDate = new Date();
        this.currentWeek = DateHelper.getWeekNumber(this.currentDate);
        this.renderWeek();
    }

    openWeekSelector() {
        const input = prompt("Später hübschen Datepicker einbauen. Bitte Wochennummer (1–52) eingeben:", this.currentWeek);
        const weekNum = parseInt(input);
        if (WeekSwitcher.validateWeek(weekNum)) {
            this.currentWeek = weekNum;
            this.renderWeek();
        }
    }
}
