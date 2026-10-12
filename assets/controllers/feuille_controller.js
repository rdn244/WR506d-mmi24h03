import { Controller } from '@hotwired/stimulus';

/*
 * Feuille modale (<dialog>) : « Publier sur Instagram ».
 * Rouverte d'elle-même quand le serveur renvoie le formulaire avec des erreurs (valeur « ouvert »).
 */
export default class extends Controller {
    static targets = ['dialogue'];
    static values = { ouvert: Boolean };

    connect() {
        // Turbo met la page en cache avant de la quitter : on la garde fermée
        this.avantCache = () => this.fermer();
        document.addEventListener('turbo:before-cache', this.avantCache);

        if (this.ouvertValue) {
            this.ouvrir();
        }
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.avantCache);
    }

    ouvrir() {
        if (!this.dialogueTarget.open) {
            this.dialogueTarget.showModal();
        }
    }

    fermer() {
        if (this.dialogueTarget.open) {
            this.dialogueTarget.close();
        }
    }

    // Un clic sur le fond assombri vise le <dialog> lui-même, pas son contenu
    clicFond(event) {
        if (event.target === this.dialogueTarget) {
            this.fermer();
        }
    }
}
