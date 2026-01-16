import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

    static targets = [
        "monthSelect",
        "trackedTimesContainer",
        "usersWorkTimeSummary",
        "singleUserTotals",
        "currentUser",
        "toastContainer"
    ];

    connect() {

        localStorage.removeItem('selectedUserId')

        let currentMonth = new Date().getMonth() + 1;
        let currentYear = new Date().getFullYear();

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

        //für jedes element in json wird ein option element im month select erstellt
        const monthNames = ["Januar", "Februar", "März", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember"];
        let latestYear = Math.max(...Object.keys(json).map(year => parseInt(year)));
        let latestMonth = Math.max(...Object.keys(json[latestYear]).map(month => parseInt(month)));

        Object.keys(json).forEach(year => {
            let optgroup = document.createElement("optgroup");
            optgroup.label = year;
            Object.keys(json[year]).forEach(month => {
                let option = document.createElement("option");
                option.text = `${monthNames[parseInt(month) - 1]} ${year}`;
                option.value = `${year}-${month}`;
                if (parseInt(year) === latestYear && parseInt(month) === latestMonth) {
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

        //suche inputs mit name shiftStartDate, shiftEndDate, shiftStartTime, shiftEndTime
        let shiftStartDate = tr.querySelector('[name="shiftStartDate"]').value
        let shiftEndDate = tr.querySelector('[name="shiftEndDate"]').value
        let shiftStartTime = tr.querySelector('[name="shiftStartTime"]').value
        let shiftEndTime = tr.querySelector('[name="shiftEndTime"]').value
        let timeTrackerId = tr.querySelector('[name="timeTrackerId"]').value

        //create array with all values
        let data = {
            shiftStartDate: shiftStartDate,
            shiftEndDate: shiftEndDate,
            shiftStartTime: shiftStartTime,
            shiftEndTime: shiftEndTime,
            timeTrackerId: timeTrackerId
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

    displayToast(message, type) {

        //post request to display a toast /toast

        fetch(`/toast`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({message: message, type: type})
        })
            .then(response => response.text()).then(html => {
            this.toastContainerTarget.innerHTML = html;

            // Attach the close event listener to the new toast close button
            const closeButton = this.toastContainerTarget.querySelector('[data-dismiss-target]');
            if (closeButton) {
                closeButton.addEventListener('click', (event) => {
                    // Find the toast element by its unique ID (using the dynamic UID)
                    const toastId = closeButton.getAttribute('data-dismiss-target').replace('#', '');
                    const toastElement = document.getElementById(toastId);

                    // Hide or remove the toast when close button is clicked
                    if (toastElement) {
                        toastElement.remove(); // Remove the toast element from the DOM
                    }
                });
            }

            // //empty container after 5 seconds
            // setTimeout(() => {
            //     this.toastContainerTarget.innerHTML = ""
            // }, 5000)
        })

    }

}
