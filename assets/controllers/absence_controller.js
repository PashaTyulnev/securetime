import { Controller } from '@hotwired/stimulus'

export default class extends Controller {

    toggleComment(event) {
        const id = event.params.id
        const area = document.getElementById(`action-area-${id}`)
        if (area) {
            area.classList.toggle('hidden')
        }
    }

    approve(event) {
        const id = event.params.id
        this.#post(`/api/absences/${id}/approve`, id)
    }

    reject(event) {
        const id = event.params.id
        this.#post(`/api/absences/${id}/reject`, id)
    }

    #post(url, id) {
        const comment = document.getElementById(`comment-${id}`)?.value || null

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ adminComment: comment })
        })
        .then(r => r.json())
        .then(data => {
            if (data.message && !data.status) {
                alert(data.message)
            } else {
                window.location.reload()
            }
        })
        .catch(() => alert('Fehler beim Verarbeiten der Anfrage.'))
    }
}
