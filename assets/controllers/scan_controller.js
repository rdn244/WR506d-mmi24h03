import { Controller } from '@hotwired/stimulus';

/*
 * Scan d'un objet (maquette 15) : la photo est réduite dans le navigateur, encodée en base64,
 * puis analysée par l'IA (OpenRouter) qui pré-remplit la fiche.
 */
const COTE_MAX = 1024;
const QUALITE = 0.82;
const ETATS = ['photo', 'analyse', 'termine', 'echec'];

export default class extends Controller {
    static targets = ['entree', 'apercu', 'invite', 'photo', 'modele', 'champ', 'titre', 'statut'];
    static values = { url: String, jeton: String, disponible: Boolean };

    connect() {
        // Formulaire renvoyé avec des erreurs : la photo déjà prise est conservée
        if (this.photoTarget.value) {
            this.afficher(this.photoTarget.value);
            this.etat('photo');
        }
    }

    disconnect() {
        this.requete?.abort();
    }

    async choisir() {
        const fichier = this.entreeTarget.files[0];
        if (!fichier) {
            return;
        }

        let dataUrl;
        try {
            dataUrl = await reduire(fichier);
        } catch {
            this.etat('echec', 'Cette photo est illisible : essaie en JPEG ou PNG.');

            return;
        }

        this.photoTarget.value = dataUrl;
        this.afficher(dataUrl);

        if (this.disponibleValue) {
            await this.analyser(dataUrl);
        } else {
            this.etat('photo', 'Complète la fiche de l\'objet.');
        }
    }

    afficher(dataUrl) {
        this.apercuTarget.src = dataUrl;
        this.apercuTarget.hidden = false;
        this.inviteTarget.hidden = true;
    }

    async analyser(dataUrl) {
        this.requete?.abort();
        this.requete = new AbortController();
        this.etat('analyse', 'Analyse en cours : l\'IA observe ta photo…');

        const donnees = new FormData();
        donnees.append('image', dataUrl);
        donnees.append('_token', this.jetonValue);

        try {
            const reponse = await fetch(this.urlValue, {
                method: 'POST',
                body: donnees,
                headers: { Accept: 'application/json' },
                signal: this.requete.signal,
            });
            const resultat = await reponse.json();

            if (!reponse.ok) {
                this.etat('echec', resultat.message ?? 'L\'analyse n\'a pas abouti : remplis la fiche à la main.');

                return;
            }

            this.remplir(resultat.champs ?? {}, resultat.modele);
            this.etat('termine', 'Vérifie les informations détectées avant d\'ajouter l\'objet.', 'Analyse terminée');
        } catch (erreur) {
            if (erreur.name !== 'AbortError') {
                this.etat('echec', 'L\'analyse n\'a pas abouti : remplis la fiche à la main.');
            }
        }
    }

    remplir(champs, modele) {
        this.modeleTarget.value = modele ?? '';

        for (const ligne of this.champTargets) {
            const valeur = champs[ligne.dataset.champ];

            // Ce que l'utilisateur a déjà saisi ou choisi n'est jamais écrasé
            if (!valeur || ligne.dataset.modifie) {
                continue;
            }

            if (ligne.dataset.champ === 'categorie') {
                const choix = ligne.querySelector(`input[type="radio"][value="${CSS.escape(String(valeur))}"]`);
                if (!choix) {
                    continue;
                }
                choix.checked = true;
                choix.nextElementSibling?.scrollIntoView({ block: 'nearest', inline: 'center' });
            } else {
                ligne.querySelector('input').value = valeur;
            }
            ligne.querySelector('.champ__ia').hidden = false;
        }
    }

    // Saisie ou choix manuel : l'étincelle « détecté par l'IA » disparaît
    modifie(event) {
        const ligne = event.target.closest('[data-champ]');
        if (ligne) {
            ligne.dataset.modifie = '1';
            ligne.querySelector('.champ__ia')?.setAttribute('hidden', '');
        }
    }

    etat(nom, message = null, titre = null) {
        this.element.classList.remove(...ETATS.map((etat) => `scan--${etat}`));
        this.element.classList.add(`scan--${nom}`);

        if (message) {
            this.statutTarget.textContent = message;
        }
        if (titre) {
            this.titreTarget.textContent = titre;
        }
    }
}

/*
 * Réduit la photo (côté le plus long à 1024 px) et la renvoie en JPEG base64 :
 * envoi et analyse bien plus rapides qu'avec l'original de l'appareil photo.
 */
async function reduire(fichier) {
    const image = await createImageBitmap(fichier, { imageOrientation: 'from-image' });
    const echelle = Math.min(1, COTE_MAX / Math.max(image.width, image.height));
    const toile = document.createElement('canvas');

    toile.width = Math.round(image.width * echelle);
    toile.height = Math.round(image.height * echelle);
    toile.getContext('2d').drawImage(image, 0, 0, toile.width, toile.height);
    image.close();

    return toile.toDataURL('image/jpeg', QUALITE);
}
