import { Controller } from '@hotwired/stimulus';

/*
 * Panneau déroulant (sélecteur de profil…) : ouverture au clic, fermeture au clic extérieur et sur Échap.
 */
export default class extends Controller {
    static targets = ['bouton', 'panneau'];

    connect() {
        this.surClicDocument = (event) => {
            if (!this.element.contains(event.target)) {
                this.fermer();
            }
        };
        this.surTouche = (event) => {
            if (event.key === 'Escape') {
                this.fermer();
                this.boutonTarget.focus();
            }
        };
        this.avantCache = () => this.fermer();
        document.addEventListener('turbo:before-cache', this.avantCache);
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.avantCache);
        this.fermer();
    }

    basculer() {
        this.element.classList.contains('deroulant--ouvert') ? this.fermer() : this.ouvrir();
    }

    ouvrir() {
        this.element.classList.add('deroulant--ouvert');
        this.boutonTarget.setAttribute('aria-expanded', 'true');
        this.panneauTarget.inert = false;
        document.addEventListener('click', this.surClicDocument);
        document.addEventListener('keydown', this.surTouche);
    }

    fermer() {
        if (!this.element.classList.contains('deroulant--ouvert')) {
            return;
        }
        this.element.classList.remove('deroulant--ouvert');
        this.boutonTarget.setAttribute('aria-expanded', 'false');
        this.panneauTarget.inert = true;
        document.removeEventListener('click', this.surClicDocument);
        document.removeEventListener('keydown', this.surTouche);
    }
}
