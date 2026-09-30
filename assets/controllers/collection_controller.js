import { Controller } from '@hotwired/stimulus';

/*
 * Ajoute dynamiquement une entrée à un CollectionType Symfony à partir de son prototype.
 */
export default class extends Controller {
    static targets = ['liste'];
    static values = {
        index: Number,
        prototype: String,
    };

    ajouter() {
        const html = this.prototypeValue.replace(/__name__/g, this.indexValue);
        this.listeTarget.insertAdjacentHTML('beforeend', html);
        this.indexValue++;
        this.listeTarget.lastElementChild?.querySelector('input')?.focus();
    }
}
