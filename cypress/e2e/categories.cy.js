import { COMPTES } from '../support/e2e';

describe('Catégories personnelles', () => {
    before(() => cy.chargerFixtures());

    beforeEach(() => cy.connexion(COMPTES.camille));

    it('crée, utilise puis supprime une catégorie', () => {
        cy.visit('/categories');
        cy.contains('a', 'Nouvelle').click();

        cy.get('input[name="categorie[nom]"]').type('Vacances');
        cy.get('label[for="categorie_icone_6"]').click();
        cy.contains('button', 'Créer la catégorie').click();

        cy.get('.flash').should('contain', 'Catégorie « Vacances » créée.');
        cy.get('.liste-cartes').should('contain', 'Vacances').and('contain', 'Sport');
        cy.screenshot('categories');

        // Proposée au scan, après les catégories prédéfinies
        cy.visit('/objets/nouveau');
        cy.get('.puces--categories label').last().should('contain', 'Vacances');

        cy.visit('/categories');
        cy.contains('.carte-ligne', 'Vacances').find('button[aria-label^="Supprimer"]').click();

        cy.get('.flash').should('contain', 'Catégorie « Vacances » supprimée.');
        cy.get('.liste-cartes').should('not.contain', 'Vacances');
    });

    it('refuse un nom déjà pris', () => {
        cy.visit('/categories/nouvelle');
        cy.get('input[name="categorie[nom]"]').type('hauts');
        cy.contains('button', 'Créer la catégorie').click();

        cy.get('.erreurs').should('contain', 'La catégorie « hauts » existe déjà.');
    });
});
