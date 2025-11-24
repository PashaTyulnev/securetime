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
        "demandShiftsContainer"
    ];

    connect() {
        this.currentDate = new Date();
        this.currentWeek = DateHelper.getWeekNumber(this.currentDate);
        this.renderWeek();
        this.checkFacilityFromUrl();
        this.initAllScheduleElements();
    }

    buildScheduleElement() {
        let year = this.currentDate.getFullYear();
        let week = this.currentWeek;

        ScheduleComponentApi.getWeekScheduleComponent(year, week).then(demandShiftsComponent => {
            this.scheduleContainerTarget.innerHTML = demandShiftsComponent;
        });
    }

    /**
     * Wochenswitcher-Funktionen
     */

    renderWeek() {
        this.currentWeekTextTarget.textContent = "Woche " + this.currentWeek;
        this.currentDateRangeTextTarget.textContent = WeekSwitcher.getWeekRangeText(this.currentWeek, this.currentDate.getFullYear());
        this.currentWeekInputTarget.value = this.currentWeek;

        this.buildScheduleElement();
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
        const facilityId = event.target.value;
        this.updateFacilityUrl(facilityId);
        this.initAllScheduleElements();
    }

    updateFacilityUrl(facilityId) {
        const url = new URL(window.location.href);
        url.searchParams.set('facility', facilityId);
        window.history.pushState({}, '', url);
    }

    checkFacilityFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const facilityId = urlParams.get('facility');
        if (facilityId) {
            this.facilitySelectTarget.value = facilityId;
        }
    }

    initAllScheduleElements() {
        this.loadDemandShifts();
    }

    loadDemandShifts() {
        const facilityId = this.facilitySelectTarget.value;

        ScheduleComponentApi.getDemandShiftsOfFacilityComponent(facilityId).then(shifts => {
            this.demandShiftsContainerTarget.innerHTML = shifts;
        });
    }

    // Drag Events - werden über data-action aufgerufen
    onDragStart(event) {
        event.target.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('text/html', event.target.outerHTML);
    }

    onDragEnd(event) {
        event.target.classList.remove('dragging');
    }

    // Drop Zone Events - werden über data-action aufgerufen
    onDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
        event.currentTarget.classList.add('drag-over');
    }

    onDragLeave(event) {
        event.currentTarget.classList.remove('drag-over');
    }

    onDrop(event) {
        event.preventDefault();
        event.currentTarget.classList.remove('drag-over');

        const dragging = document.querySelector('.dragging');
        const zone = event.currentTarget;

        // Hole die IRI vom dragged Element
        const demandShiftUri = dragging.dataset.demandShiftUri;
        const employeeUri = zone.dataset.employeeUri;
        const date = zone.dataset.date;

        this.assignShift(demandShiftUri, employeeUri, date).then(newShift => {
            ScheduleComponentApi.getMiniShiftComponent(newShift).then(miniShiftHtml => {
                zone.insertAdjacentHTML('beforeend', miniShiftHtml);
            });
        });

    }

    assignShift(demandShiftUri, employeeId, dayIndex) {

        let payload = {
            demandShift: demandShiftUri,
            employee: employeeId,
            date: dayIndex,
            note: ""
        }

        return ApiDataHandler.createNewEntity("shifts", payload).then(response => {
            return response;
        });
    }

    delete(event){
        let shiftUri = event.params.shiftUri

        // Fragen ob gelöscht werden soll
        if(!confirm("Soll diese Schicht wirklich gelöscht werden?")){
            return;
        }

        ApiDataHandler.deleteEntity("shifts",shiftUri).then(response => {
            if(response.ok){
                // Entferne das Element aus dem DOM
                const shiftElement = this.scheduleContainerTarget.querySelector(`[data-shift-uri='${shiftUri}']`);
                if(shiftElement){
                    shiftElement.remove();
                }
            } else {
                alert("Fehler beim Löschen der Schicht.");
            }
        })
    }
}
