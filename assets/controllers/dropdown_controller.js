import { Controller } from '@hotwired/stimulus'
import { Dropdown } from 'flowbite'

export default class extends Controller {
    static targets = ['trigger', 'menu', 'label']

    connect() {
        this.dropdown = new Dropdown(this.menuTarget, this.triggerTarget)
        this.updateLabel()
    }

    disconnect() {
        this.dropdown?.destroy?.()
    }

    updateLabel() {
        const checkboxes = this.element.querySelectorAll('input[type="checkbox"]')
        const checked = [...checkboxes].filter(cb => cb.checked)

        if (checked.length === 0) {
            this.labelTarget.textContent = 'Auswählen'
        } else {
            this.labelTarget.textContent = checked
                .map(cb => cb.nextElementSibling.textContent.trim())
                .join(', ')
        }
    }
}
