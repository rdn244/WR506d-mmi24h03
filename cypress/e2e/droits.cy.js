import { COMPTES } from '../support/e2e';

describe('Droits d\'accès entre membres', () => {
    let urlLook;

    before(() => cy.chargerFixtures());

    it('une amie voit les looks de Camille sans pouvoir les modifier', () => {
        cy.connexion(COMPTES.sophie);
        cy.visit('/amis');
        cy.contains('.ligne-membre__identite', 'Camille').click();

        cy.get('h1').should('contain', 'Camille');
        cy.get('.galerie .vignette').should('have.length', 2);
        cy.get('.galerie .vignette').first().click();

        cy.get('.sous-titre').should('contain', 'Look de Camille');
        cy.get('.bouton--instagram').should('not.exist');
        cy.get('.fiche__suppression').should('not.exist');
        cy.location('pathname').then((chemin) => {
            urlLook = chemin;
        });
        cy.screenshot('look-vu-par-une-amie');
    });

    it('un membre sans lien ne voit pas ce look (403)', () => {
        cy.connexion(COMPTES.nina);

        cy.request({ url: urlLook, failOnStatusCode: false }).its('status').should('eq', 403);
        cy.request({ url: `${urlLook}/photo`, failOnStatusCode: false }).its('status').should('eq', 403);
    });

    it('un objet personnel reste invisible pour l\'amie', () => {
        cy.connexion(COMPTES.camille);
        cy.visit('/objets');
        cy.contains('.vignette', 'Journal intime').invoke('attr', 'href').then((urlObjet) => {
            cy.connexion(COMPTES.sophie);
            cy.request({ url: urlObjet, failOnStatusCode: false }).its('status').should('eq', 403);
        });
    });
});
