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

    exportPdf(event){
        let currentMonth = this.currentDate.getMonth() + 1;
        let currentYear = this.currentDate.getFullYear();

        fetch(`/api/schedule/month/pdf?month=${currentMonth}&year=${currentYear}`)
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
