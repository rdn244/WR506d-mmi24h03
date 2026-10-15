import { COMPTES } from '../support/e2e';

describe('Pièces, rangements et partage', () => {
    let urlGrenier;

    before(() => {
        cy.chargerFixtures();
        cy.connexion(COMPTES.camille);
        cy.visit('/logement');
        cy.contains('.carte-ligne', 'Grenier').invoke('attr', 'href').then((url) => {
            urlGrenier = url;
        });
    });

    it('le compte lié n\'accède pas à une pièce non partagée (403)', () => {
        cy.connexion(COMPTES.alex);
        cy.request({ url: urlGrenier, failOnStatusCode: false }).its('status').should('eq', 403);
    });

    it('ajoute deux rangements, dont un partagé, puis partage la pièce', () => {
        cy.connexion(COMPTES.camille);
        cy.visit('/logement');
        cy.get('h1').should('contain', 'Appartement de Lyon');
        cy.contains('.carte-ligne', 'Grenier').click();
        cy.get('h1').should('contain', 'Grenier');

        cy.get('.ajout-contenant summary').click();
        cy.get('input[name="contenant[nom]"]').type('Malle en osier');
        cy.get('input[name="contenant[type]"]').first().check({ force: true });
        cy.get('input[name="contenant[partage]"]').check({ force: true });
        cy.contains('button', 'Ajouter le rangement').click();
        cy.contains('.carte-ligne', 'Malle en osier').should('contain', 'Partagé');

        cy.get('.ajout-contenant summary').click();
        cy.get('input[name="contenant[nom]"]').type('Étagère à chaussures');
        cy.get('input[name="contenant[type]"]').first().check({ force: true });
        cy.contains('button', 'Ajouter le rangement').click();
        cy.contains('.carte-ligne', 'Étagère à chaussures').should('not.contain', 'Partagé');

        // L'interrupteur de la pièce l'ouvre au compte lié
        cy.get('.partage [role="switch"]').should('have.attr', 'aria-checked', 'false').click();
        cy.get('.partage [role="switch"]').should('have.attr', 'aria-checked', 'true');
        cy.screenshot('piece-partagee');
    });

    it('le compte lié voit maintenant la pièce', () => {
        cy.connexion(COMPTES.alex);
        cy.visit(urlGrenier);

        cy.get('h1').should('contain', 'Grenier');
        cy.get('.liste-cartes').should('contain', 'Malle en osier').and('contain', 'Étagère à chaussures');
    });

    it('l\'amie ne voit que le rangement partagé et ne peut rien ajouter', () => {
        cy.connexion(COMPTES.sophie);
        cy.visit(urlGrenier);

        cy.get('.liste-cartes').should('contain', 'Malle en osier').and('not.contain', 'Étagère à chaussures');
        cy.get('.ajout-contenant').should('not.exist');
        cy.get('.partage [role="switch"]').should('not.exist');
    });

    it('un membre sans lien n\'accède pas à la pièce (403)', () => {
        cy.connexion(COMPTES.nina);
        cy.request({ url: urlGrenier, failOnStatusCode: false }).its('status').should('eq', 403);
    });
});
