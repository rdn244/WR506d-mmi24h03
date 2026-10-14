import { COMPTES } from '../support/e2e';

describe('Connexion', () => {
    before(() => cy.chargerFixtures());

    it('refuse un mauvais mot de passe', () => {
        cy.visit('/connexion');
        cy.get('input[name="email"]').type(COMPTES.camille);
        cy.get('input[name="password"]').type('mauvais-mot-de-passe');
        cy.contains('button', 'Se connecter').click();

        cy.get('.erreurs--formulaire').should('contain', 'Identifiants invalides.');
        cy.location('pathname').should('eq', '/connexion');
    });

    it('ouvre l\'accueil avec les catégories et les derniers objets', () => {
        cy.connexion(COMPTES.camille);
        cy.visit('/accueil');

        cy.get('h1').should('contain', 'Bonjour Camille');
        cy.get('.statistique').first().should('contain', '11').and('contain', 'Objets');
        cy.get('.section-accueil .filtre').first().should('contain', 'Hauts');
        cy.get('.section-accueil .liste-cartes .carte-ligne').should('have.length', 4);
        cy.screenshot('accueil');
    });

    it('se déconnecte', () => {
        cy.connexion(COMPTES.camille);
        cy.visit('/profil');
        cy.contains('button', 'Déconnexion').click();

        cy.location('pathname').should('match', /^\/(connexion)?$/);
    });
});
