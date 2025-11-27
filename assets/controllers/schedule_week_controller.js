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
        this.currentDate = new Date();
        this.currentWeek = DateHelper.getWeekNumber(this.currentDate);
        this.checkFacilityFromUrl();
        this.initializeSchedule();
    }

    initializeSchedule() {
        // Beide Promises parallel laden, aber auf beide warten
        Promise.all([
            this.buildScheduleElement(),
            this.loadDemandShifts()
        ]).then(() => {
            // Erst wenn BEIDE fertig sind, Progress initialisieren
            this.initDemandShiftProgress();
        });
    }

    buildScheduleElement() {
        let year = this.currentDate.getFullYear();
        let week = this.currentWeek;

        // WICHTIG: Promise zurückgeben!
        return ScheduleComponentApi.getWeekScheduleComponent(year, week).then(demandShiftsComponent => {
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

        // Bei Week-Changes auch auf beide warten
        Promise.all([
            this.buildScheduleElement(),
            this.loadDemandShifts()
        ]).then(() => {
            this.initDemandShiftProgress();
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
        const facilityId = event.target.value;
        this.updateFacilityUrl(facilityId);

        // Auch hier beide laden und dann Progress
        Promise.all([
            this.buildScheduleElement(),
            this.loadDemandShifts()
        ]).then(() => {
            this.initDemandShiftProgress();
        });
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
        this.loadDemandShifts().then(r => {
            this.initDemandShiftProgress();
        });
    }

    loadDemandShifts() {
        const facilityId = this.facilitySelectTarget.value;

        // WICHTIG: Promise zurückgeben!
        return ScheduleComponentApi.getDemandShiftsOfFacilityComponent(facilityId).then(shifts => {
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

        const demandShiftUri = dragging.dataset.demandShiftUri;
        const demandShiftId = dragging.dataset.demandShiftId;
        const employeeUri = zone.dataset.employeeUri;
        const date = zone.dataset.date;

        // Ganze Woche zuweisen
        if (zone.dataset.wholeWeek === "true") {
            this.assignWholeWeek(demandShiftUri, demandShiftId, employeeUri, date);
            return;
        }

        // Einzelne Schicht zuweisen
        this.assignSingleShift(demandShiftUri, employeeUri, date, zone);
    }

    assignSingleShift(demandShiftUri, employeeUri, date, zone) {
        this.assignShift(demandShiftUri, employeeUri, date).then(newShift => {
            if (newShift.status === 201) {
                ScheduleComponentApi.getMiniShiftComponent(newShift.data).then(miniShiftHtml => {
                    zone.insertAdjacentHTML('beforeend', miniShiftHtml);
                    this.displayToast('success', 'Schicht erfolgreich zugewiesen.');

                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            this.initDemandShiftProgress();
                        });
                    });
                });
            } else {
                this.displayToast("error", newShift.data.detail);
            }
        });
    }

    assignWholeWeek(demandShiftUri, demandShiftId, employeeUri, mondayDate) {
        // DemandShift Element holen
        const demandShiftElement = this.demandShiftDraggableTargets.find(
            el => el.dataset.demandShiftId === demandShiftId
        );

        if (!demandShiftElement) {
            this.displayToast("error", "DemandShift nicht gefunden");
            return;
        }

        // Aktive Tage ermitteln
        const activeDays = this.getActiveDaysForWholeWeek(demandShiftElement);

        // Datums-Array für die ganze Woche erstellen
        const weekDates = this.generateWeekDates(mondayDate, activeDays);

        if (weekDates.length === 0) {
            this.displayToast("error", "Keine aktiven Tage für diese Schicht");
            return;
        }

        // Rekursiv alle Schichten zuweisen
        this.assignShiftsRecursively(demandShiftUri, employeeUri, weekDates, 0);
    }

    assignShiftsRecursively(demandShiftUri, employeeUri, dates, index, successCount = 0, errorCount = 0, errors = []) {
        // Abbruchbedingung: Alle Tage abgearbeitet
        if (index >= dates.length) {
            // Toast-Meldung basierend auf Erfolg/Fehler
            this.displayBulkAssignmentToast(successCount, errorCount, errors);

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    this.initDemandShiftProgress();
                });
            });
            return;
        }

        const currentDate = dates[index];

        // Finde die richtige Drop-Zone für dieses Datum
        const targetZone = this.findDropZoneForDate(employeeUri, currentDate);

        if (!targetZone) {
            // Nächsten Tag (Zone nicht gefunden zählt als Fehler)
            this.assignShiftsRecursively(
                demandShiftUri,
                employeeUri,
                dates,
                index + 1,
                successCount,
                errorCount + 1,
                [...errors, { date: currentDate, reason: 'Drop-Zone nicht gefunden' }]
            );
            return;
        }

        // Schicht zuweisen
        this.assignShift(demandShiftUri, employeeUri, currentDate).then(newShift => {
            if (newShift.status === 201) {
                ScheduleComponentApi.getMiniShiftComponent(newShift.data).then(miniShiftHtml => {
                    targetZone.insertAdjacentHTML('beforeend', miniShiftHtml);

                    // Rekursiv nächsten Tag (Erfolg)
                    this.assignShiftsRecursively(
                        demandShiftUri,
                        employeeUri,
                        dates,
                        index + 1,
                        successCount + 1,
                        errorCount,
                        errors
                    );
                });
            } else {
                // Bei Fehler trotzdem weitermachen
                console.error(`Fehler beim Zuweisen für ${currentDate}:`, newShift.data.detail);

                this.assignShiftsRecursively(
                    demandShiftUri,
                    employeeUri,
                    dates,
                    index + 1,
                    successCount,
                    errorCount + 1,
                    [...errors, { date: currentDate, reason: newShift.data.detail }]
                );
            }
        }).catch(error => {
            // Network error oder anderer Fehler
            console.error(`Fehler beim Zuweisen für ${currentDate}:`, error);

            this.assignShiftsRecursively(
                demandShiftUri,
                employeeUri,
                dates,
                index + 1,
                successCount,
                errorCount + 1,
                [...errors, { date: currentDate, reason: error.message }]
            );
        });
    }

    displayBulkAssignmentToast(successCount, errorCount, errors) {
        const total = successCount + errorCount;

        if (errorCount === 0) {
            // Alle erfolgreich
            this.displayToast('success', `Alle ${successCount} Schichten erfolgreich zugewiesen`);
        } else if (successCount === 0) {
            // Alle fehlgeschlagen
            const errorMessage = this.formatBulkErrors(errors);
            this.displayToast('error', `Fehler: Keine Schichten zugewiesen. ${errorMessage}`);
        } else {
            // Teilweise erfolgreich
            const errorMessage = this.formatBulkErrors(errors);
            this.displayToast('error', `${successCount} von ${total} Schichten zugewiesen. ${errorMessage}`);
        }
    }

    formatBulkErrors(errors) {
        if (errors.length === 0) return '';

        // Nur die erste Fehlermeldung anzeigen (für bessere Lesbarkeit)
        const firstError = errors[0];
        const dateFormatted = new Date(firstError.date).toLocaleDateString('de-DE', {
            weekday: 'short',
            day: '2-digit',
            month: '2-digit'
        });

        if (errors.length === 1) {
            return `${dateFormatted}: ${firstError.reason}`;
        } else {
            return `${dateFormatted}: ${firstError.reason} (+${errors.length - 1} weitere)`;
        }
    }

    findDropZoneForDate(employeeUri, dateString) {
        // Suche alle Drop-Zonen und finde die richtige für Employee + Datum
        const allZones = document.querySelectorAll('.drop-zone');

        for (const zone of allZones) {
            if (zone.dataset.employeeUri === employeeUri &&
                zone.dataset.date === dateString) {
                return zone;
            }
        }

        return null;
    }

    getActiveDaysForWholeWeek(demandShiftElement) {
        const days = [
            { index: 0, key: 'onMonday' },
            { index: 1, key: 'onTuesday' },
            { index: 2, key: 'onWednesday' },
            { index: 3, key: 'onThursday' },
            { index: 4, key: 'onFriday' },
            { index: 5, key: 'onSaturday' },
            { index: 6, key: 'onSunday' }
        ];

        return days
            .filter(day => {
                const dataKey = day.key.charAt(0).toLowerCase() + day.key.slice(1);
                return demandShiftElement.dataset[dataKey] === 'true' ||
                    demandShiftElement.dataset[dataKey] === '1';
            })
            .map(day => day.index);
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

    assignShift(demandShiftUri, employeeId, dayIndex) {

        let payload = {
            demandShift: demandShiftUri,
            employee: employeeId,
            date: dayIndex,
            note: ""
        }


        return ApiDataHandler.createNewEntity("shifts", payload,true)
            .then(res => {
                return res;
            });
    }

    delete(event) {
        let shiftUri = event.params.shiftUri

        // Fragen ob gelöscht werden soll
        if (!confirm("Soll diese Schicht wirklich gelöscht werden?")) {
            return;
        }

        ApiDataHandler.deleteEntity("shifts", shiftUri).then(response => {
            if (response.ok) {
                // Entferne das Element aus dem DOM
                const shiftElement = this.scheduleContainerTarget.querySelector(`[data-shift-uri='${shiftUri}']`);
                if (shiftElement) {
                    shiftElement.remove();
                    this.displayToast('success', 'Schicht erfolgreich gelöscht.');
                    this.initDemandShiftProgress();
                }
            } else {
                alert("Fehler beim Löschen der Schicht.");
            }
        })
    }

    displayToast(type, message) {
        const toast = document.getElementById('toast-default');
        const toastMessage = document.getElementById('toast-message');

        toastMessage.innerText = message;

        // Toast einblenden
        toast.classList.remove('opacity-0', 'translate-y-2');
        toast.style.zIndex = '9999';

        if(type === 'success') {
            toast.classList.remove('errorToastBackground');
            toast.classList.add('successToastBackground');
        }else{
            toast.classList.remove('successToastBackground');
            toast.classList.add('errorToastBackground');
        }



        // Toast nach 3 Sekunden ausblenden
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-2');

            toast.style.zIndex = '0';
        }, 10050)


    }

    initDemandShiftProgress() {
        // 1. Alle vergebenen Schichten sammeln
        let allMiniShiftPills = this.miniShiftPillTargets;

        // 2. Map erstellen: demandShiftId -> { weekday: count }
        let demandShiftCountMap = {};

        allMiniShiftPills.forEach(shiftPill => {
            let demandShiftId = shiftPill.dataset.demandShiftId;
            let date = shiftPill.dataset.date; // Format: "2024-01-15"

            if (!demandShiftId || !date) return;

            // Wochentag ermitteln (0 = Sonntag, 1 = Montag, etc.)
            let dayOfWeek = new Date(date).getDay();
            // Zu deutschem Format konvertieren (0 = Montag, 6 = Sonntag)
            let germanDay = dayOfWeek === 0 ? 6 : dayOfWeek - 1;

            // Initialisiere Map für diese DemandShift falls noch nicht vorhanden
            if (!demandShiftCountMap[demandShiftId]) {
                demandShiftCountMap[demandShiftId] = {
                    0: 0, // Montag
                    1: 0, // Dienstag
                    2: 0, // Mittwoch
                    3: 0, // Donnerstag
                    4: 0, // Freitag
                    5: 0, // Samstag
                    6: 0  // Sonntag
                };
            }

            // Count erhöhen
            demandShiftCountMap[demandShiftId][germanDay]++;
        });

        // 3. Alle DemandShift-Elemente aktualisieren
        this.updateDemandShiftElements(demandShiftCountMap);
    }

    updateDemandShiftElements(countMap) {
        this.demandShiftDraggableTargets.forEach(demandShiftElement => {
            let demandShiftId = demandShiftElement.dataset.demandShiftId;
            let counts = countMap[demandShiftId] || {0: 0, 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0};

            // Benötigte Anzahl Personen aus dem Element holen
            let requiredCount = parseInt(demandShiftElement.dataset.requiredCount || 0);

            // Tages-Badges aktualisieren (Mo, Di, Mi, Do, Fr, Sa, So)
            this.updateDayBadges(demandShiftElement, counts, requiredCount);

            // Gesamtfortschritt berechnen und aktualisieren
            this.updateOverallProgress(demandShiftElement, counts, requiredCount);
        });
    }

    updateDayBadges(element, counts, requiredCount) {
        // Tages-Container finden
        const dayStatusContainer = element.querySelector('.flex.justify-between.gap-1');
        if (!dayStatusContainer) return;

        // Alle Tages-Badges durchgehen (Mo bis So)
        const dayBadges = dayStatusContainer.querySelectorAll('.flex.flex-col.items-center');

        dayBadges.forEach((badge, index) => {
            const circle = badge.querySelector('.rounded-full:not(.bg-black\\/10)');
            if (!circle) return;

            const count = counts[index] || 0;
            const isFilled = count >= requiredCount;
            const isPartial = count > 0 && count < requiredCount;

            // Klassen zurücksetzen
            circle.classList.remove(
                'bg-green-400/30', 'border-green-400/50',
                'bg-orange-400/30', 'border-orange-400/50',
                'bg-red-400/30', 'border-red-400/50'
            );

            // Neue Klassen setzen
            if (isFilled) {
                circle.classList.add('bg-green-400/30', 'border-green-400/50');
            } else if (isPartial) {
                circle.classList.add('bg-orange-400/30', 'border-orange-400/50');
            } else {
                circle.classList.add('bg-red-400/30', 'border-red-400/50');
            }

            // Content aktualisieren
            if (count > 0) {
                let textColorClass = isFilled ? 'text-green-200' : (isPartial ? 'text-orange-200' : 'text-red-200');
                circle.innerHTML = `<span class="text-[9px] font-bold ${textColorClass}">${count}</span>`;
            } else {
                circle.innerHTML = `<svg class="h-2.5 w-2.5 text-red-200"><use href="#lucide-x"></use></svg>`;
            }
        });
    }

    updateOverallProgress(element, counts, requiredCount) {
        // Aktive Tage zählen (aus dem Template-Data)
        const activeDays = this.getActiveDays(element);

        // Gesamtzahl zugewiesener Personen über alle aktiven Tage
        let totalAssigned = 0;
        let totalRequired = 0;

        activeDays.forEach(dayIndex => {
            totalAssigned += counts[dayIndex] || 0;
            totalRequired += requiredCount;
        });

        const fulfillmentPercentage = totalRequired > 0 ? Math.round((totalAssigned / totalRequired) * 100) : 0;

        // Status-Badge (oben rechts) aktualisieren
        this.updateStatusBadge(element, fulfillmentPercentage);

        // Personen-Counter aktualisieren
        this.updatePersonCounter(element, totalAssigned, totalRequired);

        // Fortschrittsbalken aktualisieren
        this.updateProgressBar(element, fulfillmentPercentage);
    }

    getActiveDays(element) {
        // Prüfe welche Tage aktiv sind (onMonday, onTuesday, etc.)
        const activeDays = [];
        const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        days.forEach((day, index) => {
            const dataKey = `on${day.charAt(0).toUpperCase() + day.slice(1)}`;
            if (element.dataset[dataKey] === 'true' || element.dataset[dataKey] === '1') {
                activeDays.push(index);
            }
        });

        // Fallback: wenn keine Info vorhanden, alle Tage als aktiv annehmen
        return activeDays.length > 0 ? activeDays : [0, 1, 2, 3, 4, 5, 6];
    }

    updateStatusBadge(element, percentage) {
        const badge = element.querySelector('.absolute.-top-2.-right-2');
        if (!badge) return;

        const badgeInner = badge.firstElementChild;

        // Klassen zurücksetzen
        badgeInner.classList.remove('bg-green-500', 'bg-orange-500', 'bg-red-500');

        if (percentage >= 100) {
            badgeInner.classList.add('bg-green-500');
            badgeInner.innerHTML = '<svg class="h-4 w-4 text-white"><use href="#lucide-check"></use></svg>';
        } else if (percentage > 0) {
            badgeInner.classList.add('bg-orange-500');
            badgeInner.innerHTML = `<span class="text-[10px] font-bold text-white">${percentage}%</span>`;
        } else {
            badgeInner.classList.add('bg-red-500');
            badgeInner.innerHTML = '<svg class="h-4 w-4 text-white"><use href="#lucide-alert-circle"></use></svg>';
        }
    }

    updatePersonCounter(element, assigned, required) {
        const counterContainer = element.querySelector('.flex.items-center.gap-1\\.5.px-2.py-1.rounded-lg.bg-white\\/10');
        if (!counterContainer) return;

        const counterSpan = counterContainer.querySelector('.font-semibold.text-white.whitespace-nowrap');
        if (!counterSpan) return;

        const isFilled = assigned >= required;
        const isPartial = assigned > 0 && assigned < required;

        const colorClass = isFilled ? 'text-green-200' : (isPartial ? 'text-orange-200' : 'text-red-200');

        counterSpan.innerHTML = `
        <span class="${colorClass}">${assigned}</span>
        <span class="text-white/60">/</span>
        ${required}
    `;
    }

    updateProgressBar(element, percentage) {
        const progressBar = element.querySelector('.w-full.h-1.bg-black\\/20 .h-full');
        if (!progressBar) return;

        // Klassen zurücksetzen
        progressBar.classList.remove('bg-green-400', 'bg-orange-400', 'bg-red-400');

        // Neue Farbe setzen
        if (percentage >= 100) {
            progressBar.classList.add('bg-green-400');
        } else if (percentage > 0) {
            progressBar.classList.add('bg-orange-400');
        } else {
            progressBar.classList.add('bg-red-400');
        }

        // Breite animiert setzen
        progressBar.style.width = `${Math.min(percentage, 100)}%`;
    }
}
