import { COMPTES } from '../support/e2e';

describe('Relations entre membres', () => {
    let urlLook;

    before(() => {
        cy.chargerFixtures();
        cy.connexion(COMPTES.camille);
        cy.visit('/looks');
        cy.get('.galerie .vignette').first().invoke('attr', 'href').then((url) => {
            urlLook = url;
        });
    });

    it('Nina trouve Camille et lui demande d\'être amies', () => {
        cy.connexion(COMPTES.nina);
        // Sans lien, le look de Camille (compte privé) est interdit
        cy.request({ url: urlLook, failOnStatusCode: false }).its('status').should('eq', 403);

        cy.visit('/amis');
        cy.get('input[name="q"]').type('Camille{enter}');
        cy.contains('.ligne-membre', 'Camille').contains('button', 'Ajouter').click();

        cy.get('.flash').should('contain', 'Demande d\'ami envoyée à Camille.');
        cy.contains('h2', 'Demandes envoyées (1)').should('exist');
    });

    it('Camille accepte : Nina voit alors ses looks', () => {
        cy.connexion(COMPTES.camille);
        cy.visit('/amis');
        cy.contains('h2', 'Demandes reçues (1)').should('exist');
        cy.contains('.ligne-membre', 'Nina').contains('button', 'Accepter').click();

        cy.get('.flash').should('contain', 'Toi et Nina êtes maintenant amis.');
        cy.contains('h2', 'Amis (2)').should('exist');
        cy.screenshot('amis');

        cy.connexion(COMPTES.nina);
        cy.request(urlLook).its('status').should('eq', 200);
    });

    it('Nina s\'abonne puis se désabonne depuis le profil de Camille', () => {
        cy.connexion(COMPTES.nina);
        cy.visit('/amis');
        cy.contains('.ligne-membre__identite', 'Camille').click();

        cy.contains('button', 'S\'abonner').click();
        cy.get('.flash').should('contain', 'Tu suis maintenant Camille.');
        cy.contains('button', 'Se désabonner').click();
        cy.contains('button', 'S\'abonner').should('exist');
    });

    it('Camille invite Sophie à lier leurs comptes, Sophie accepte', () => {
        cy.connexion(COMPTES.camille);
        cy.visit('/comptes-lies');
        cy.get('input[name="invitation[email]"]').type(COMPTES.sophie);
        cy.contains('button', 'Inviter').click();
        cy.get('.flash').should('contain', 'Invitation envoyée à Sophie.');

        cy.connexion(COMPTES.sophie);
        cy.visit('/comptes-lies');
        cy.contains('li, .ligne-membre', 'Camille').contains('button', 'Accepter').click();
        cy.get('.flash').should('contain', 'Vos comptes sont maintenant liés avec Camille.');
    });

    it('un email inconnu ne crée aucune invitation', () => {
        cy.connexion(COMPTES.camille);
        cy.visit('/comptes-lies');
        cy.get('input[name="invitation[email]"]').type('personne@dressing.test');
        cy.contains('button', 'Inviter').click();

        cy.get('.flash').should('contain', 'Aucun compte Dressing n\'utilise cet email.');
    });

    it('Camille bloque Nina : l\'amitié disparaît et le look redevient interdit', () => {
        cy.connexion(COMPTES.camille);
        cy.visit('/amis');
        cy.contains('.ligne-membre__identite', 'Nina').click();
        cy.contains('button', 'Bloquer').click();

        cy.get('.flash').should('contain', 'Nina est bloqué.');
        cy.contains('button', 'Débloquer').should('exist');
        cy.contains('button', 'Ajouter en ami').should('not.exist');

        cy.connexion(COMPTES.nina);
        cy.request({ url: urlLook, failOnStatusCode: false }).its('status').should('eq', 403);
    });

    it('Camille débloque Bruno depuis sa liste', () => {
        cy.connexion(COMPTES.camille);
        cy.visit('/amis');
        cy.contains('h2', 'Utilisateurs bloqués (2)').should('exist');
        cy.contains('.ligne-membre', 'Bruno').contains('button', 'Débloquer').click();

        cy.contains('h2', 'Utilisateurs bloqués (1)').should('exist');
    });
});
