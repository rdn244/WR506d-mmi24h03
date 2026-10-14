import { Controller } from '@hotwired/stimulus';

/*
 * Aperçu de la photo choisie avant l'envoi du formulaire.
 */
export default class extends Controller {
    static targets = ['entree', 'image', 'invite'];

    disconnect() {
        this.liberer();
    }

    afficher() {
        this.liberer();
        const fichier = this.entreeTarget.files[0];

        if (!fichier) {
            this.imageTarget.hidden = true;
            this.inviteTarget.hidden = false;

            return;
        }

        this.url = URL.createObjectURL(fichier);
        this.imageTarget.src = this.url;
        this.imageTarget.hidden = false;
        this.inviteTarget.hidden = true;
    }

    liberer() {
        if (this.url) {
            URL.revokeObjectURL(this.url);
            this.url = null;
        }
    }
}
