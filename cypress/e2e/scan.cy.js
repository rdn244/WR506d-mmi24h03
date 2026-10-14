import { COMPTES } from '../support/e2e';

describe('Scan IA d\'un objet', () => {
    before(() => cy.chargerFixtures());

    beforeEach(() => cy.connexion(COMPTES.camille));

    it('pré-remplit la fiche à partir de la photo puis ajoute l\'objet', () => {
        cy.visit('/objets/nouveau');

        // L'IA est simulée : la réponse d'OpenRouter ne dépend ni du réseau ni du quota gratuit
        cy.contains('.puces--categories label', 'Hauts').invoke('attr', 'for').then((id) => {
            cy.get(`#${id}`).invoke('val').then((categorie) => {
                cy.intercept('POST', '/objets/analyser', {
                    champs: { nom: 'T-shirt rouge Nike', marque: 'Nike', type: 'T-shirt', matiere: 'Coton', couleur: 'Rouge', categorie: Number(categorie) },
                    modele: 'modele-simule:free',
                }).as('analyse');
            });
        });

        cy.get('input[type="file"][data-scan-target="entree"]').selectFile('cypress/fixtures/tshirt-nike.png', { force: true });

        // La photo part réduite et encodée en base64
        cy.wait('@analyse').its('request.body').should('include', 'data:image/jpeg;base64');
        cy.get('h1').should('contain', 'Analyse terminée');
        cy.get('input[name="objet[nom]"]').should('have.value', 'T-shirt rouge Nike');
        cy.get('input[name="objet[marque]"]').should('have.value', 'Nike');
        cy.get('[data-champ="categorie"] input:checked').next('label').should('contain', 'Hauts');
        cy.get('[data-champ="marque"] .champ__ia').should('be.visible');
        cy.screenshot('scan-analyse-terminee');

        cy.contains('button', 'Valider et ajouter à mon dressing').click();

        cy.get('.flash').should('contain', '« T-shirt rouge Nike » est ajouté à ton dressing.');
        cy.get('.etiquette--categorie').should('contain', 'Hauts');
        cy.get('.mention').should('contain', 'modele-simule:free');
        cy.screenshot('fiche-objet');
    });

    it('laisse remplir la fiche à la main quand l\'IA échoue', () => {
        cy.intercept('POST', '/objets/analyser', { statusCode: 502, body: { message: 'Les modèles gratuits sont saturés.' } });
        cy.visit('/objets/nouveau');

        cy.get('input[type="file"][data-scan-target="entree"]').selectFile('cypress/fixtures/tshirt-nike.png', { force: true });

        cy.get('[data-scan-target="statut"]').should('contain', 'saturés');
        cy.get('input[name="objet[nom]"]').type('T-shirt rouge');
        cy.contains('button', 'Valider et ajouter à mon dressing').click();

        cy.get('h1').should('contain', 'T-shirt rouge');
    });
});
