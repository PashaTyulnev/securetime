import { Controller } from "@hotwired/stimulus";
import DateHelper from "../helper/date_helper.js";

export default class extends Controller {
    static targets = ["currentWeekText", "currentDateRangeText", "currentWeekInput"];

    connect() {
        this.currentDate = new Date();
        this.currentWeek = DateHelper.getWeekNumber(this.currentDate);
        this.renderWeek();
    }

    renderWeek() {
        this.currentWeekTextTarget.textContent = "Woche " + this.currentWeek;

        const firstDate = DateHelper.getFirstDateOfWeek(this.currentWeek, this.currentDate.getFullYear());
        const lastDate = DateHelper.getLastDateOfWeek(this.currentWeek, this.currentDate.getFullYear());

        const rangeText =
            firstDate.toLocaleDateString('de-DE', { day: 'numeric', month: 'long' }).replace('.', '') +
            " - " +
            lastDate.toLocaleDateString('de-DE', { day: 'numeric', month: 'long' }).replace('.', '');

        this.currentDateRangeTextTarget.textContent = rangeText;
        this.currentWeekInputTarget.value = this.currentWeek;
    }

    previousWeek() {
        this.currentWeek--;
        if (this.currentWeek < 1) {
            this.currentWeek = 52;
            this.currentDate.setFullYear(this.currentDate.getFullYear() - 1);
        }
        this.renderWeek();
    }

    nextWeek() {
        this.currentWeek++;
        if (this.currentWeek > 52) {
            this.currentWeek = 1;
            this.currentDate.setFullYear(this.currentDate.getFullYear() + 1);
        }
        this.renderWeek();
    }

    today() {
        this.currentDate = new Date();
        this.currentWeek = DateHelper.getWeekNumber(this.currentDate);
        this.renderWeek();
    }

    openWeekSelector() {
        // einfache Variante: prompt (später evtl. schönerer Picker)
        const input = prompt("Später hübschen Datepicker einbauen. Bitte Wochennummer (1–52) eingeben:", this.currentWeek);
        const weekNum = parseInt(input);
        if (!isNaN(weekNum) && weekNum >= 1 && weekNum <= 52) {
            this.currentWeek = weekNum;
            this.renderWeek();
        }
    }
}
