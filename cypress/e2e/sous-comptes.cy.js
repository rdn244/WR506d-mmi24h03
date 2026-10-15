import { COMPTES, MOT_DE_PASSE } from '../support/e2e';

describe('Sous-comptes (profils de la famille)', () => {
    before(() => cy.chargerFixtures());

    beforeEach(() => cy.connexion(COMPTES.camille));

    it('crée le profil d\'un enfant en vue limitée', () => {
        cy.visit('/sous-comptes');
        cy.get('a.carte-profil').should('have.length', 2);
        cy.get('.carte-ajout').click();

        cy.get('input[name="sous_compte[prenom]"]').type('Jules');
        cy.get('input[name="sous_compte[dateNaissance]"]').type('2016-03-10');
        cy.get('input[name="sous_compte[niveau]"][value="vue_limitee"]').check({ force: true });
        cy.contains('button', 'Créer le profil').click();

        cy.get('.flash').should('contain', 'Le profil de Jules est créé.');
        cy.get('a.carte-profil').should('have.length', 3).last().should('contain', 'Jules');
    });

    it('passe sur le profil de l\'enfant, qui perd les droits du compte principal', () => {
        cy.visit('/profil');
        cy.get('.selecteur-profil__bouton').click();
        cy.contains('button.selecteur-profil__ligne', 'Jules').click();

        cy.location('pathname').should('eq', '/accueil');
        cy.visit('/profil');
        cy.get('.selecteur-profil__libelle').should('contain', 'Jules');
        cy.screenshot('profil-enfant-actif');

        // Gestion du compte, création de look et nouvel objet : réservés au compte principal
        cy.request({ url: '/profil/parametres', failOnStatusCode: false }).its('status').should('eq', 403);
        cy.request({ url: '/sous-comptes', failOnStatusCode: false }).its('status').should('eq', 403);
        cy.request({ url: '/looks/nouveau', failOnStatusCode: false }).its('status').should('eq', 403);

        // Vue limitée : l'inventaire se consulte, sans bouton de modification
        cy.visit('/objets');
        cy.get('.vignette').should('have.length', 10);
        cy.contains('.vignette', 'Manteau bordeaux').click();
        cy.contains('a', 'Modifier').should('not.exist');
    });

    it('revient au compte principal avec son mot de passe', () => {
        cy.visit('/profils/principal');
        cy.get('h1').should('contain', 'Retour au compte principal');

        cy.get('input[name="compte_principal[motDePasse]"]').type('mauvais', { log: false });
        cy.contains('button', 'Continuer').click();
        cy.get('.erreurs').should('exist');

        cy.get('input[name="compte_principal[motDePasse]"]').type(MOT_DE_PASSE, { log: false });
        cy.contains('button', 'Continuer').click();

        cy.location('pathname').should('eq', '/accueil');
        cy.visit('/profil');
        cy.get('.selecteur-profil__libelle').should('contain', 'Compte principal');
    });

    it('supprime le profil de l\'enfant', () => {
        cy.visit('/sous-comptes');
        cy.contains('a.carte-profil', 'Jules').click();
        cy.contains('button', 'Supprimer le profil').click();

        cy.get('.flash').should('contain', 'Le profil est supprimé.');
        cy.get('a.carte-profil').should('have.length', 2);
    });
});
