import { Controller } from '@hotwired/stimulus';
import {Modal} from 'flowbite';

export default class extends Controller {

    static targets = ["modal"]

    connect() {
        let modalElement = this.modalTarget;

        let modalId = Math.random().toString(36).substring(2, 15)

        //modale eine random id geben
        modalElement.id = modalId;

        // options mit Standardwerten
        const options = {
            onHide: () => {
                console.log('Modal wird geschlossen');
            }
        };

        const instanceOptions = {
            id: modalId,
            override: true
        };

        this.modal = new Modal(modalElement, options, instanceOptions);
        this.modal.show();
    }

    hide(event) {
       this.modal.hide()
    }

}
