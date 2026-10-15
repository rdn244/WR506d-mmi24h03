import { COMPTES } from '../support/e2e';

describe('Looks', () => {
    before(() => cy.chargerFixtures());

    beforeEach(() => cy.connexion(COMPTES.camille));

    it('refuse un look sans photo', () => {
        cy.visit('/looks/nouveau');
        cy.get('input[name="look[titre]"]').type('Sans photo');
        cy.contains('button', 'Enregistrer le look').click();

        cy.location('pathname').should('eq', '/looks/nouveau');
        cy.get('.erreurs').should('exist');
    });

    it('crée un look avec sa photo, le consulte puis le supprime', () => {
        cy.visit('/looks');
        cy.get('.galerie .vignette').should('have.length', 2);
        cy.visit('/looks/nouveau');

        cy.get('input[name="look[photo]"]').selectFile('cypress/fixtures/tshirt-nike.png', { force: true });
        cy.get('.depot-photo__image').should('be.visible');
        cy.get('input[name="look[titre]"]').type('Sport du dimanche');
        cy.get('textarea[name="look[description]"]').type('T-shirt Nike et jogging');
        cy.contains('button', 'Enregistrer le look').click();

        cy.get('.flash').should('contain', 'Ton look est enregistré.');
        cy.get('h1').should('contain', 'Sport du dimanche');
        cy.get('.fiche__description').should('contain', 'T-shirt Nike et jogging');
        // La photo est servie par le contrôleur, après le contrôle des droits
        cy.get('img[src$="/photo"]').should('be.visible').and(($img) => {
            expect($img[0].naturalWidth).to.be.greaterThan(0);
        });
        // Sans compte Instagram relié, l'appli invite à le connecter
        cy.get('.invite-instagram').should('contain', 'Connecte d\'abord ton compte Instagram');
        cy.screenshot('look-cree');

        cy.contains('button', 'Supprimer le look').click();
        cy.get('.flash').should('contain', 'Le look est supprimé.');
        cy.get('.galerie .vignette').should('have.length', 2);
    });
});
