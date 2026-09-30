import { Controller } from '@hotwired/stimulus';

/*
 * Menu d'ajout ouvert par le bouton « + » : fermeture au clic extérieur, sur Échap
 * et au clic sur le voile ; le focus revient sur le bouton à la fermeture.
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
        // Évite qu'une page mise en cache par Turbo réapparaisse avec le menu ouvert
        this.avantCache = () => this.fermer();
        document.addEventListener('turbo:before-cache', this.avantCache);
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.avantCache);
        this.fermer();
    }

    basculer() {
        this.estOuvert() ? this.fermer() : this.ouvrir();
    }

    ouvrir() {
        this.element.classList.add('menu-ajout--ouvert');
        this.boutonTarget.setAttribute('aria-expanded', 'true');
        this.panneauTarget.inert = false;
        document.addEventListener('click', this.surClicDocument);
        document.addEventListener('keydown', this.surTouche);
        this.panneauTarget.querySelector('a')?.focus({ preventScroll: true });
    }

    fermer() {
        if (!this.estOuvert()) {
            return;
        }
        this.element.classList.remove('menu-ajout--ouvert');
        this.boutonTarget.setAttribute('aria-expanded', 'false');
        this.panneauTarget.inert = true;
        document.removeEventListener('click', this.surClicDocument);
        document.removeEventListener('keydown', this.surTouche);
    }

    estOuvert() {
        return this.element.classList.contains('menu-ajout--ouvert');
    }
}
