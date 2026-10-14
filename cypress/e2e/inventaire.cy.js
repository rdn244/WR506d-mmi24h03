import { COMPTES } from '../support/e2e';

describe('Inventaire', () => {
    before(() => cy.chargerFixtures());

    beforeEach(() => {
        cy.connexion(COMPTES.camille);
        cy.visit('/objets');
    });

    it('affiche tout l\'inventaire avec les puces de catégories', () => {
        cy.get('.sous-titre').should('contain', '11 objets');
        cy.get('.galerie .vignette').should('have.length', 11);
        cy.get('.filtre--actif').should('contain', 'Tous');
        cy.screenshot('inventaire');
    });

    it('filtre par catégorie', () => {
        cy.contains('.filtre', 'Hauts').click();

        cy.get('.filtre--actif').should('contain', 'Hauts');
        cy.get('.galerie .vignette').should('have.length', 2);
        cy.get('.galerie').should('contain', 'T-shirt Nike').and('contain', 'Pull marine');
    });

    it('recherche par marque', () => {
        cy.get('input[name="q"]').type('sandro{enter}');

        cy.get('.galerie .vignette').should('have.length', 1).and('contain', 'Manteau bordeaux');
    });

    it('ouvre la fiche d\'un objet et la modifie', () => {
        cy.contains('.vignette', 'Boîte à couture').click();
        cy.contains('a', 'Modifier la fiche').click();

        cy.contains('.puces--categories label', 'Déco').click();
        cy.get('input[name="objet[type]"]').clear().type('Boîte en bois');
        cy.contains('button', 'Enregistrer').click();

        cy.get('.flash').should('contain', 'La fiche est mise à jour.');
        cy.get('.etiquette--categorie').should('contain', 'Déco');
        cy.get('.caracteristiques').should('contain', 'Boîte en bois');
    });
});
