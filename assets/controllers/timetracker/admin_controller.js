import {Controller} from '@hotwired/stimulus';
import {Modal} from 'flowbite';

export default class extends Controller {

    static targets = [
        "monthSelect",
        "trackedTimesContainer",
        "usersWorkTimeSummary",
        "singleUserTotals",
        "currentUser",
        "toastContainer",
        "employeeSelect",
        "entryDate",
        "timeFrom",
        "timeTo"
    ];

    connect() {

        localStorage.removeItem('selectedUserId')

        // Aktueller Kalendermonat (bis 24:00 Uhr gültig, kein UTC)
        const now = new Date();
        let currentMonth = now.getMonth() + 1;
        let currentYear = now.getFullYear();

        this.getTrackedTimes(currentYear, currentMonth)

        this.getCurrentUserInformation()

        this.getAvailableMonths()

        document.addEventListener("timetracker:updated", () => {
            this.getCurrentUserInformation();
        });

        // setInterval(() => {
        //     this.getCurrentUserInformation(true);
        // }, 5000);
    }

    displayAllUserTimes(event) {

        localStorage.removeItem('selectedUserId')
        this.currentUserTarget.value = ""
        this.selectBackground()
        let month = this.getSelectedMonth()
        let year = this.getSelectedYear()

        this.getTrackedTimes(year, month)


        //hide single user totals
        this.singleUserTotalsTarget.innerHTML = ""
        let displayAllButton = document.getElementById('displayAllButton')

        //add active class to the selected user time row to the end
        displayAllButton.classList.add('active')
    }

    getSelectedYear() {
        let selectedMonth = this.monthSelectTarget.value;

        //split month and year
        selectedMonth = selectedMonth.split("-")

        return selectedMonth[0]

    }

    getSelectedMonth() {
        let selectedMonth = this.monthSelectTarget.value;

        //split month and year
        selectedMonth = selectedMonth.split("-")

        return selectedMonth[1]
    }

    triggerSelectedMonth(event) {

        let month = this.getSelectedMonth()
        let year = this.getSelectedYear()

        //check if a user is selected
        let selectedUserId = localStorage.getItem('selectedUserId')

        //save year and month to local storage
        localStorage.setItem('selectedYear', year);
        localStorage.setItem('selectedMonth', month);

        if (selectedUserId !== null) {
            this.getTrackedTimes(year, month, selectedUserId)
        } else {
            this.getTrackedTimes(year, month)
        }

    }

    getCurrentUserInformation(isRefreshByTimer = false) {
        {

            //get request to get the tracked times for the selected month
            fetch(`/api/timetracker/last_user_information`).then(response => response.json()).then(json => {

                fetch(`/time_tracking_overview/lastUserTimeTrackingInformationTable`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(json)
                    }
                ).then(response => response.text()).then(html => {
                    this.trackedTimesContainerTarget.innerHTML = html;
                    this.selectBackground()
                    this.executeSingleUserTotals(this.currentUserTarget.value, isRefreshByTimer)

                })
            })
        }
    }

    getTrackedTimes(year, month, userId = null) {

        let uri = `/api/timetracker/overview_all?year=${year}&month=${month}`
        if (userId !== null) {
            uri += `&userId=${userId}`
        }
        //get request to get the tracked times for the selected month
        fetch(uri).then(response => response.json()).then(json => {

            //if json is empty, return
            if (Object.keys(json).length === 0) {
                this.usersWorkTimeSummaryTarget.innerHTML = "Keine Zeiteinträge gefunden"
                return
            }


            //save json to local storage
            localStorage.setItem('currentTrackedTimes', JSON.stringify(json));

            //auch für alle user wenn kein user ausgewählt ist
            this.loadSingleUserTotals(json)


            fetch(`/time_tracking_overview/trackedTimesTable`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(json)
                }
            ).then(response => response.text()).then(html => {
                this.usersWorkTimeSummaryTarget.innerHTML = html;

            })
        })
    }

    loadSingleUserData(event) {

        let userId = event.params.userId

        this.executeSingleUserTotals(userId)
    }

    executeSingleUserTotals(userId, isRefreshByTimer = false) {

        if (isRefreshByTimer) {
            return
        }
        let selectedMonth = this.monthSelectTarget.value;

        //save userId to local storage
        localStorage.setItem('selectedUserId', userId);

        this.currentUserTarget.value = userId
        this.selectBackground()

        //split month and year
        selectedMonth = selectedMonth.split("-")

        let month = selectedMonth[1]
        let year = selectedMonth[0]

        //save to local storage witch user id was selected
        localStorage.setItem('selectedUserId', userId);

        this.getTrackedTimes(year, month, userId)
    }

    loadTrackedTimesTable() {
        //get request to get the tracked times for the selected month
        fetch(`/time_tracking_overview/trackedTimesTable`).then(response => response.text()).then(html => {
            this.trackedTimesContainerTarget.innerHTML = html;
        })
    }

    getAvailableMonths() {
        //get request to get the available months
        fetch(`/api/timetracker/availableMonths`).then(response => response.json()).then(json => {
            this.createMonthOptionsDropdown(json)
        })
    }

    createMonthOptionsDropdown(json) {

        // Standard: aktueller Kalendermonat (bis 24:00 Uhr)
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1;

        const monthNames = ["Januar", "Februar", "März", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember"];

        // Aktuellen Monat in die Liste aufnehmen, falls noch keine Einträge existieren
        if (!json[currentYear]) {
            json[currentYear] = {};
        }
        if (!json[currentYear][currentMonth]) {
            json[currentYear][currentMonth] = String(currentMonth);
        }

        const years = Object.keys(json).map(y => parseInt(y)).sort((a, b) => a - b);

        years.forEach(year => {
            let optgroup = document.createElement("optgroup");
            optgroup.label = String(year);
            const months = Object.keys(json[year]).map(m => parseInt(m)).sort((a, b) => a - b);
            months.forEach(month => {
                let option = document.createElement("option");
                option.text = `${monthNames[month - 1]} ${year}`;
                option.value = `${year}-${month}`;
                if (year === currentYear && month === currentMonth) {
                    option.selected = true;
                }
                optgroup.appendChild(option);
            });
            this.monthSelectTarget.appendChild(optgroup);
        });
    }

    loadSingleUserTotals(json) {
        fetch(`/api/timetracker/user_totals`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(json)
            }
        ).then(response => response.json()).then(json => {
            fetch(`/time_tracking_overview/singleUserTotals`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(json)
                }
            ).then(response => response.text()).then(html => {
                this.singleUserTotalsTarget.innerHTML = html;
            })
        })
    }

    selectBackground() {
        let userId = localStorage.getItem('selectedUserId')
        let userTimeRow = document.getElementById(`userTimeRow-${userId}`)
        let displayAllButton = document.getElementById('displayAllButton')

        // Entferne active class von allen user time rows
        let userTimeRows = document.getElementsByClassName('userTimeRow')
        for (let i = 0; i < userTimeRows.length; i++) {
            userTimeRows[i].classList.remove('active')
        }

        // Entferne active class vom displayAllButton
        displayAllButton.classList.remove('active')

        // Prüfe ob userId leer ist oder das Element nicht existiert
        if (userId === null || userId === "" || userTimeRow === null) {
            displayAllButton.classList.add('active')
            return
        }

        // Füge active class zum gewählten Element hinzu
        userTimeRow.classList.add('active')
    }

    displaySaveButton(event) {

        let target = event.target

        let tr = target.closest('tr')

        //search button with class saveChangedTimeEntryButton
        let saveButton = tr.getElementsByClassName('saveChangedTimeEntryButton')[0]

        //remove hidden class from button
        saveButton.classList.remove('hidden')

    }

    exportPdf(event) {
        event.preventDefault();

        let route = '/api/timetracker/pdf';

        let payload = localStorage.getItem('currentTrackedTimes');

        fetch(route, {
            method: 'POST',
            body: payload
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const disposition = response.headers.get('Content-Disposition');
                let filename = 'Erfasste Zeiten.pdf'; // Fallback

                if (disposition && disposition.includes('filename=')) {
                    const match = disposition.match(/filename="?([^"]+)"?/);
                    if (match && match[1]) {
                        filename = match[1];
                    }
                }

                return response.blob().then(blob => ({blob, filename}));
            })
            .then(({blob, filename}) => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            })
            .catch(error => {
                console.error('PDF Download fehlgeschlagen:', error);
            });
    }

    saveChangedTimeEntry(event) {

        let target = event.target

        //alle inputs aus der Reihe
        let tr = target.closest('tr')

        if (!tr) {
            this.displayToast('Interner Fehler: Zeile konnte nicht ermittelt werden.', 'danger')
            return
        }

        // suche inputs mit name shiftStartDate, shiftEndDate, shiftStartTime, shiftEndTime
        const shiftStartDateInput = tr.querySelector('[name="shiftStartDate"]')
        const shiftStartTimeInput = tr.querySelector('[name="shiftStartTime"]')
        const shiftEndDateInput = tr.querySelector('[name="shiftEndDate"]')
        const shiftEndTimeInput = tr.querySelector('[name="shiftEndTime"]')
        const timeTrackerIdInput = tr.querySelector('[name="timeTrackerId"]')

        // grundlegende Felder müssen vorhanden sein
        if (!shiftStartDateInput || !shiftStartTimeInput || !timeTrackerIdInput) {
            this.displayToast('Interner Fehler: Bearbeitungsfelder fehlen.', 'danger')
            return
        }

        const shiftStartDate = shiftStartDateInput.value
        const shiftStartTime = shiftStartTimeInput.value
        const timeTrackerId = timeTrackerIdInput.value

        const hasShiftEnd = shiftEndDateInput?.value && shiftEndTimeInput?.value

        let data = {
            shiftStartDate: shiftStartDate,
            shiftStartTime: shiftStartTime,
            timeTrackerId: timeTrackerId
        }
        if (hasShiftEnd) {
            data.shiftEndDate = shiftEndDateInput.value
            data.shiftEndTime = shiftEndTimeInput.value
        }

        //send data to backend
        fetch(`/api/timetracker/saveChangedTimeEntry`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
            .then(async response => {
                if (response.status === 400) {
                    const errorText = await response.text(); // Holt die Fehlermeldung als Text
                    throw new Error(errorText);
                }
                return response.json();
            })
            .then(json => {
                //if json is empty, return
                if (Object.keys(json).length === 0) {
                    return;
                }

                //if json is not empty, reload the tracked times
                this.getTrackedTimes(this.getSelectedYear(), this.getSelectedMonth(), this.currentUserTarget.value);
            })
            .catch(error => {
                let message = error.message
                message = JSON.parse(message)

                this.displayToast(message.message, 'danger')
            });

    }

    deleteTimeEntry(event) {
        const tr = event.target.closest('tr');
        const timeTrackerIdInput = tr.querySelector('[name="timeTrackerId"]');
        if (!timeTrackerIdInput) return;

        const timeTrackerId = timeTrackerIdInput.value;
        const confirmMessage = event.target.dataset.deleteConfirm || event.target.closest('[data-delete-confirm]')?.dataset?.deleteConfirm || 'Diesen Zeiteintrag inkl. aller zugehörigen Einträge unwiderruflich löschen?';
        if (!confirm(confirmMessage)) return;

        fetch('/api/timetracker/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ timeTrackerId: timeTrackerId })
        })
            .then(async (response) => {
                const json = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(json.message || 'Löschen fehlgeschlagen.');
                }
                return json;
            })
            .then(() => {
                this.getTrackedTimes(this.getSelectedYear(), this.getSelectedMonth(), this.currentUserTarget.value || null);
                if (typeof this.displayToast === 'function') {
                    this.displayToast(event.target.dataset.deleteSuccess || event.target.closest('[data-delete-success]')?.dataset?.deleteSuccess || 'Zeiteintrag wurde gelöscht.', 'success');
                }
            })
            .catch((error) => {
                if (typeof this.displayToast === 'function') {
                    this.displayToast(error.message || 'Fehler beim Löschen.', 'danger');
                }
            });
    }

    displayToast(message, type) {
        // Nutze das globale Toast-Element aus base.html.twig
        const toastElement = document.getElementById('toast-default');
        const toastMessage = document.getElementById('toast-message');

        if (!toastElement || !toastMessage) {
            // Fallback: Browser-Alert
            alert(message);
            return;
        }

        toastMessage.textContent = message;

        // Mappe Typen auf Styles (danger => error)
        const toastType = type === 'danger' ? 'error' : type;

        if (toastType === 'error') {
            toastElement.classList.remove('bg-white', 'dark:bg-gray-800', 'text-gray-900', 'dark:text-gray-100');
            toastElement.classList.add('bg-red-100', 'dark:bg-red-900', 'text-red-800', 'dark:text-red-200');
        } else {
            toastElement.classList.remove('bg-red-100', 'dark:bg-red-900', 'text-red-800', 'dark:text-red-200');
            toastElement.classList.add('bg-white', 'dark:bg-gray-800', 'text-gray-900', 'dark:text-gray-100');
        }

        // Toast einblenden
        toastElement.classList.remove('hidden', 'translate-y-2', 'opacity-0');
        toastElement.classList.add('opacity-100');
        toastElement.style.zIndex = '9999';

        // Close-Button Handler
        const closeButton = toastElement.querySelector('[data-dismiss-target]');
        if (closeButton) {
            const closeHandler = () => {
                this.hideToast();
                closeButton.removeEventListener('click', closeHandler);
            };
            closeButton.addEventListener('click', closeHandler);
        }

        // Automatisch nach 5 Sekunden ausblenden
        setTimeout(() => {
            this.hideToast();
        }, 5000);
    }

    hideToast() {
        const toastElement = document.getElementById('toast-default');
        if (toastElement) {
            toastElement.classList.add('hidden', 'translate-y-2', 'opacity-0');
            toastElement.classList.remove('opacity-100');
        }
    }

    openCreateTimeEntryModal(event) {
        event.preventDefault();
        
        // Finde das Modal-Element für Zeiteintrag-Erstellung
        // Suche nach Modal mit data-modal-no-autoshow Attribut
        const modalElement = document.querySelector('[data-modal-no-autoshow]');
        if (!modalElement) {
            console.error('Modal für Zeiteintrag-Erstellung nicht gefunden');
            return;
        }
        
        // Lade Mitarbeiter
        this.loadEmployees();
        
        // Setze heutiges Datum als Standard
        const today = new Date().toISOString().split('T')[0];
        if (this.hasEntryDateTarget && this.entryDateTarget) {
            this.entryDateTarget.value = today;
        }
        
        // Öffne Modal über den Modal-Controller
        const modalController = this.application.getControllerForElementAndIdentifier(modalElement, 'modal');
        if (modalController) {
            modalController.show();
        } else {
            // Fallback: Direkt über Flowbite Modal-Instanz
            const modalId = modalElement.id;
            if (modalId) {
                const flowbiteModal = Modal.getInstance(modalElement);
                if (flowbiteModal) {
                    flowbiteModal.show();
                } else {
                    // Erstelle neue Modal-Instanz falls noch nicht vorhanden
                    const options = {
                        onShow: () => {
                            setTimeout(() => {
                                if (typeof initFlowbite === 'function') {
                                    initFlowbite();
                                }
                            }, 100);
                        }
                    };
                    const newModal = new Modal(modalElement, options);
                    newModal.show();
                }
            }
        }
    }

    loadEmployees() {
        fetch('/api/timetracker/employees')
            .then(response => response.json())
            .then(employees => {
                const select = this.employeeSelectTarget;
                select.innerHTML = '<option value="">Bitte auswählen</option>';
                
                employees.forEach(employee => {
                    const option = document.createElement('option');
                    option.value = employee.id;
                    option.textContent = `${employee.firstName} ${employee.lastName}`;
                    select.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Fehler beim Laden der Mitarbeiter:', error);
                this.displayToast('Fehler beim Laden der Mitarbeiter', 'danger');
            });
    }

    createManualTimeEntry(event) {
        event.preventDefault();

        const employeeId = this.employeeSelectTarget.value;
        const date = this.entryDateTarget.value;
        const timeFrom = this.timeFromTarget.value;
        const timeTo = this.timeToTarget.value;

        if (!employeeId || !date || !timeFrom) {
            this.displayToast('Bitte Mitarbeiter, Datum und Startzeit auswählen.', 'danger');
            return;
        }

        const data = {
            userId: parseInt(employeeId),
            date: date,
            timeFrom: timeFrom
        };
        if (timeTo) {
            data.timeTo = timeTo;
        }
        
        fetch('/api/timetracker/create_manual_entry', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(async response => {
            const result = await response.json();
            if (response.status === 400) {
                throw new Error(result.message || 'Fehler beim Erstellen des Zeiteintrags');
            }
            return result;
        })
        .then(result => {
            if (result.message) {
                this.displayToast(result.message, 'success');
                
                // Schließe Modal
                const modal = document.querySelector('[data-controller*="modal"]');
                if (modal) {
                    const modalController = this.application.getControllerForElementAndIdentifier(modal, 'modal');
                    if (modalController) {
                        modalController.hide();
                    }
                }
                
                // Aktualisiere die Zeiteinträge
                let month = this.getSelectedMonth();
                let year = this.getSelectedYear();
                this.getTrackedTimes(year, month);
                
                // Reset Form
                this.employeeSelectTarget.value = '';
                this.timeFromTarget.value = '';
                this.timeToTarget.value = '';
            }
        })
        .catch(error => {
            console.error('Fehler beim Erstellen des Zeiteintrags:', error);
            this.displayToast(error.message || 'Fehler beim Erstellen des Zeiteintrags', 'danger');
        });
    }

}
