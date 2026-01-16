import {Controller} from '@hotwired/stimulus';

export default class extends Controller {

    static targets = ["checkinButton", "checkoutButton", "pauseinButton", "workingSince"];

    connect() {
        this.getCurrentWorkStatus();
    }

    checkin(event) {

        //fetch get reqeust
        fetch('/api/timetracker/checkin', {

        }).then(response => {
            this.getCurrentWorkStatus()
            document.dispatchEvent(new Event("timetracker:updated")); // Event auslösen
        })
    }

    checkout(event) {
        //fetch get reqeust
        fetch('/api/timetracker/checkout', {

        }).then(response => {
            this.getCurrentWorkStatus()
            document.dispatchEvent(new Event("timetracker:updated")); // Event auslösen
        })
    }

    pausein(event) {
        //fetch get reqeust
        fetch('/api/timetracker/pausein', {

        }).then(response => {
            this.getCurrentWorkStatus()
            document.dispatchEvent(new Event("timetracker:updated")); // Event auslösen
        })
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