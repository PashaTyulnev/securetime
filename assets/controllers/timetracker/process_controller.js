import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

    static targets = ["checkinButton", "checkoutButton", "pauseinButton", "workingSince"];

    connect() {
        this.getCurrentWorkStatus();
    }

    checkin(event) {
        fetch('/api/timetracker/checkin', {
            method: 'GET'
        })
        .then(response => {
            return response.json().then(data => {
                if (!response.ok) {
                    // Fehler anzeigen
                    this.showError(data.message || 'Fehler beim Checkin');
                    throw new Error(data.message || 'Fehler beim Checkin');
                }
                // Erfolg
                this.showSuccess(data.message || 'Erfolgreich eingecheckt');
                this.getCurrentWorkStatus();
                document.dispatchEvent(new Event("timetracker:updated"));
            });
        })
        .catch(error => {
            // Fehler wurde bereits angezeigt
            console.error('Checkin error:', error);
        });
    }

    checkout(event) {
        fetch('/api/timetracker/checkout', {
            method: 'GET'
        })
        .then(response => {
            return response.json().then(data => {
                if (!response.ok) {
                    // Fehler anzeigen
                    this.showError(data.message || 'Fehler beim Checkout');
                    throw new Error(data.message || 'Fehler beim Checkout');
                }
                // Erfolg
                this.showSuccess(data.message || 'Erfolgreich ausgecheckt');
                this.getCurrentWorkStatus();
                document.dispatchEvent(new Event("timetracker:updated"));
            });
        })
        .catch(error => {
            // Fehler wurde bereits angezeigt
            console.error('Checkout error:', error);
        });
    }

    pausein(event) {
        fetch('/api/timetracker/pausein', {
            method: 'GET'
        })
        .then(response => {
            return response.json().then(data => {
                if (!response.ok) {
                    // Fehler anzeigen
                    this.showError(data.message || 'Fehler beim Pause starten');
                    throw new Error(data.message || 'Fehler beim Pause starten');
                }
                // Erfolg
                this.showSuccess(data.message || 'Pause gestartet');
                this.getCurrentWorkStatus();
                document.dispatchEvent(new Event("timetracker:updated"));
            });
        })
        .catch(error => {
            // Fehler wurde bereits angezeigt
            console.error('Pause error:', error);
        });
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showSuccess(message) {
        this.showToast(message, 'success');
    }

    showToast(message, type) {
        // Verwende das vorhandene Toast-Element
        const toastElement = document.getElementById('toast-default');
        const toastMessage = document.getElementById('toast-message');
        
        if (!toastElement || !toastMessage) {
            // Fallback: Alert verwenden
            alert(message);
            return;
        }

        // Setze Nachricht
        toastMessage.textContent = message;

        // Setze Farbe basierend auf Typ
        if (type === 'error') {
            toastElement.classList.remove('bg-white', 'dark:bg-gray-800', 'text-gray-900', 'dark:text-gray-100');
            toastElement.classList.add('bg-red-100', 'dark:bg-red-900', 'text-red-800', 'dark:text-red-200');
        } else {
            toastElement.classList.remove('bg-red-100', 'dark:bg-red-900', 'text-red-800', 'dark:text-red-200');
            toastElement.classList.add('bg-white', 'dark:bg-gray-800', 'text-gray-900', 'dark:text-gray-100');
        }

        // Zeige Toast
        toastElement.classList.remove('hidden', 'translate-y-2', 'opacity-0');
        toastElement.classList.add('opacity-100');
        toastElement.style.zIndex = '9999';

        // Close-Button Event Listener hinzufügen
        const closeButton = toastElement.querySelector('[data-dismiss-target]');
        if (closeButton) {
            const closeHandler = () => {
                this.hideToast();
                closeButton.removeEventListener('click', closeHandler);
            };
            closeButton.addEventListener('click', closeHandler);
        }

        // Automatisch ausblenden nach 5 Sekunden
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

    getCurrentWorkStatus() {
        //fetch get reqeust
        fetch('/api/timetracker/status').then(response => {
            return response.json();
        }).then(data => {

            if(data.timestamp === undefined){
                return
            }
            let statusInformation = ""
            let date;
            
            if (data.type === 'checkin') {
                this.checkinButtonTarget.classList.add('hidden');
                this.checkoutButtonTarget.classList.remove('hidden');
                this.pauseinButtonTarget.classList.remove('hidden');
                statusInformation = "Du arbeitest seit:"
                date = new Date(data.mainCheckin.date);

            } else if (data.type === 'pause') {
                this.checkinButtonTarget.classList.remove('hidden');
                this.checkoutButtonTarget.classList.add('hidden');
                this.pauseinButtonTarget.classList.add('hidden');
                statusInformation = "Du hast pause seit:"
                date = new Date(data.timestamp.date);
            } else {
                this.checkinButtonTarget.classList.remove('hidden');
                this.checkoutButtonTarget.classList.add('hidden');
                this.pauseinButtonTarget.classList.add('hidden');
                statusInformation = "Du hast dich ausgecheckt am"
                date = new Date(data.timestamp.date);
            }


            let formattedDate = date.toLocaleString()

            this.workingSinceTarget.innerHTML =statusInformation + "<br>" + formattedDate


        })

    }
}