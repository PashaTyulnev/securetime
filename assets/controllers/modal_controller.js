import { Controller } from '@hotwired/stimulus';
import {Modal, initFlowbite} from 'flowbite';

export default class extends Controller {

    static targets = ["modal"]

    connect() {
        let modalElement = this.modalTarget;

        // Stelle sicher, dass das Modal-Element eine ID hat
        // Flowbite benötigt eine ID für die Initialisierung
        
        // WICHTIG: Wenn data-modal-target="modal" verwendet wird, 
        // muss das Modal-Element die ID "modal" haben, sonst gibt Flowbite Warnungen
        // Aber nur wenn noch keine ID gesetzt ist (um Konflikte zu vermeiden)
        const modalTargetAttr = this.element.getAttribute('data-modal-target');
        if (!modalElement.id) {
            if (modalTargetAttr === 'modal') {
                // Setze ID auf "modal" wenn data-modal-target="modal" verwendet wird
                // Das verhindert Flowbite-Warnungen
                modalElement.id = 'modal';
            } else {
                // Für andere Fälle: eindeutige ID erstellen
                modalElement.id = 'modal-' + Math.random().toString(36).substring(2, 15);
            }
        }

        // options mit Standardwerten
        const options = {
            onShow: () => {
                // Flowbite Komponenten nach dem Öffnen des Modals initialisieren
                setTimeout(() => {
                    try {
                        initFlowbite();
                    } catch (e) {
                        // Ignoriere Flowbite-Initialisierungsfehler
                    }
                }, 100);
            },
            onHide: () => {
                // Modal wird geschlossen
            }
        };

        // Initialisiere Flowbite Modal direkt mit dem Element
        // Verwende das Element direkt, nicht über ID-Suche
        try {
            // Erstelle Modal-Instanz ohne ID-Suche
            this.modal = new Modal(modalElement, options, {
                id: modalElement.id,
                override: true
            });
        } catch (error) {
            // Falls Flowbite-Fehler auftreten, verwende Fallback
            console.warn('Flowbite Modal konnte nicht initialisiert werden, verwende Fallback:', error);
            this.modal = {
                show: () => {
                    modalElement.classList.remove('hidden');
                    modalElement.setAttribute('aria-hidden', 'false');
                },
                hide: () => {
                    modalElement.classList.add('hidden');
                    modalElement.setAttribute('aria-hidden', 'true');
                }
            };
        }

        // Standard: Modals automatisch öffnen, außer es ist explizit deaktiviert
        const noAutoShow = modalElement.hasAttribute('data-modal-no-autoshow');
        if (!noAutoShow) {
            this.modal.show();
        }
    }

    hide(event) {
       this.modal.hide()
    }

    show() {
        if (this.modal) {
            this.modal.show();
        }
    }

}
