import { COMPTES, MOT_DE_PASSE } from '../support/e2e';

describe('Profil et paramètres', () => {
    const nouveauMotDePasse = 'nouveau-mot-de-passe-456';

    before(() => cy.chargerFixtures());

    it('modifie son nom et rend son compte public', () => {
        cy.connexion(COMPTES.alex);
        cy.visit('/profil/parametres');

        cy.get('input[name="profil[nom]"]').clear().type('Martin-Durand');
        cy.get('input[name="profil[comptePublic]"]').check({ force: true });
        cy.contains('button', 'Enregistrer').click();

        cy.get('.flash').should('contain', 'Ton profil a été mis à jour.');
        cy.get('input[name="profil[nom]"]').should('have.value', 'Martin-Durand');
        cy.get('input[name="profil[comptePublic]"]').should('be.checked');
    });

    it('change son mot de passe et se reconnecte avec le nouveau', () => {
        cy.connexion(COMPTES.alex);
        cy.visit('/profil/parametres');

        cy.get('input[name="mot_de_passe[actuel]"]').type(MOT_DE_PASSE, { log: false });
        cy.get('input[name="mot_de_passe[nouveau][first]"]').type(nouveauMotDePasse, { log: false });
        cy.get('input[name="mot_de_passe[nouveau][second]"]').type(nouveauMotDePasse, { log: false });
        cy.contains('button', 'Changer le mot de passe').click();

        // L'utilisateur reste connecté après le changement
        cy.get('.flash').should('contain', 'Ton mot de passe a été modifié.');
        cy.location('pathname').should('eq', '/profil/parametres');

        // L'ancien mot de passe ne fonctionne plus, le nouveau oui
        cy.clearCookies();
        cy.visit('/connexion');
        cy.get('input[name="email"]').type(COMPTES.alex);
        cy.get('input[name="password"]').type(MOT_DE_PASSE, { log: false });
        cy.contains('button', 'Se connecter').click();
        cy.get('.erreurs--formulaire').should('contain', 'Identifiants invalides.');

        cy.connexion(COMPTES.alex, nouveauMotDePasse);
        cy.visit('/accueil');
        cy.get('h1').should('contain', 'Bonjour Alex');
    });

    it('refuse un mot de passe actuel erroné', () => {
        cy.connexion(COMPTES.camille);
        cy.visit('/profil/parametres');

        cy.get('input[name="mot_de_passe[actuel]"]').type('pas-le-bon', { log: false });
        cy.get('input[name="mot_de_passe[nouveau][first]"]').type(nouveauMotDePasse, { log: false });
        cy.get('input[name="mot_de_passe[nouveau][second]"]').type(nouveauMotDePasse, { log: false });
        cy.contains('button', 'Changer le mot de passe').click();

        cy.get('.erreurs').should('exist');
    });
});
