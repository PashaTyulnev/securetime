import {Controller} from "@hotwired/stimulus";
import WeekSwitcher from "../service/week_switcher.js";
import DateHelper from "../helper/date_helper.js";
import ScheduleComponentApi from "../api/schedule/ScheduleComponentApi.js";
import ApiDataHandler from "../api/ApiDataHandler/ApiDataHandler.js";

export default class extends Controller {
    static targets = [
        "demandShiftDraggable",
        "demandShiftDropZone",
        "currentWeekText",
        "currentDateRangeText",
        "currentWeekInput",
        "scheduleContainer",
        "facilityShiftsContainer",
        "facilitySelect",
        "demandShiftsContainer",
        "miniShiftPill"
    ];

    connect() {
        this.weekSpan = 2;

        this.currentDate = new Date();
        this.currentWeek = DateHelper.getWeekNumber(this.currentDate);
        this.renderWeek()
        this.buildScheduleElement()
    }

    buildScheduleElement() {
        let year = this.currentDate.getFullYear();
        let week = this.currentWeek;

        let selectedFacilityId = this.selectedFacilityIdValue ? this.selectedFacilityIdValue : null;

        return ScheduleComponentApi.getTwoWeekScheduleComponent(year, week, selectedFacilityId).then(demandShiftsComponent => {
            this.scheduleContainerTarget.innerHTML = demandShiftsComponent;
        });
    }

    /**
     * Wochenswitcher-Funktionen
     */

    renderWeek() {
        const endWeek = this.currentWeek + this.weekSpan - 1;
        this.currentWeekTextTarget.textContent = `Woche ${this.currentWeek}-${endWeek}`;

        const startRange = WeekSwitcher.getWeekRangeText(this.currentWeek, this.currentDate.getFullYear());
        const endDate = new Date(this.currentDate);
        endDate.setDate(endDate.getDate() + (this.weekSpan * 7) - 1);
        const endRange = WeekSwitcher.getWeekRangeText(endWeek, endDate.getFullYear());

        // Kombiniere Start- und Enddatum
        const [startDay] = startRange.split(' - ');
        const [, endDay] = endRange.split(' - ');
        this.currentDateRangeTextTarget.textContent = `${startDay} - ${endDay}`;

        this.currentWeekInputTarget.value = this.currentWeek;

        Promise.all([
            this.buildScheduleElement(),
        ]).then(() => {
        });
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

    selectFacility(event) {

        this.selectedFacilityIdValue = event.target.value;

        this.buildScheduleElement()
    }

    generateWeekDates(mondayDateString, activeDays) {
        const monday = new Date(mondayDateString);
        const weekDates = [];

        activeDays.forEach(dayIndex => {
            const date = new Date(monday);
            date.setDate(monday.getDate() + dayIndex);

            // Format: YYYY-MM-DD
            const dateString = date.toISOString().split('T')[0];
            weekDates.push(dateString);
        });

        return weekDates;
    }


    exportPdf(event) {

        let currentYear = this.currentDate.getFullYear();
        let currentWeek = this.currentWeek;
        let weekSpan = this.weekSpan;

        let selectedFacilityId = this.selectedFacilityIdValue ? this.selectedFacilityIdValue : null;

        fetch(`/api/schedule/week/pdf?week=${currentWeek}&weekSpan=${weekSpan}&year=${currentYear}&facilityId=${selectedFacilityId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return Promise.all([response.blob(), response.headers.get('Content-Disposition')]);
            })
            .then(([blob, contentDisposition]) => {
                // Blob in ein URL-Objekt umwandeln
                const url = URL.createObjectURL(blob);

                // Link-Element erzeugen
                const a = document.createElement('a');
                a.href = url;

                // Den Dateinamen aus dem Content-Disposition-Header extrahieren
                if (contentDisposition) {
                    const match = contentDisposition.match(/filename[^;=\n]*=(([\"']).*?\2|[^;\n]*)/i);
                    if (match) {
                        a.download = match[1].replace(/"/g, ''); // Umgebende Anführungszeichen entfernen
                    } else {
                        a.download = 'download.pdf'; // Standardname, falls kein Dateiname gefunden wird
                    }
                } else {
                    a.download = 'download.pdf'; // Standardname, falls kein Header vorhanden ist
                }
                a.click();

                // URL-Objekt wieder freigeben
                URL.revokeObjectURL(url);
            })

    }


}
