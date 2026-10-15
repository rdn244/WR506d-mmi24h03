describe('Inscription et premier logement', () => {
    const email = `lea.${Date.now()}@dressing.test`;

    before(() => cy.chargerFixtures());

    it('crée un compte, décrit son logement puis choisit ses pièces', () => {
        cy.visit('/inscription');
        cy.get('input[name="inscription[prenom]"]').type('Léa');
        cy.get('input[name="inscription[email]"]').type(email);
        cy.get('input[name="inscription[plainPassword]"]').type('motdepasse123', { log: false });
        cy.contains('button', 'Continuer').click();

        // Le compte est connecté et enchaîne sur la description du logement
        cy.location('pathname').should('eq', '/logements/nouveau');
        cy.get('input[name="logement[nom]"]').type('Maison de Nantes');
        cy.get('input[name="logement[type]"][value="maison"]').check({ force: true });
        cy.get('input[name="logement[adresse]"]').type('Nantes');
        cy.contains('button', 'Continuer').click();

        // Les pièces les plus courantes sont cochées par défaut ; on ajoute le dressing
        cy.location('pathname').should('match', /^\/logements\/\d+\/pieces$/);
        cy.get('input[name="pieces[types][]"]:checked').should('have.length.at.least', 1);
        cy.get('input[name="pieces[types][]"][value="dressing"]').check({ force: true });
        cy.contains('button', 'Terminer et découvrir mon dressing').click();

        cy.location('pathname').should('match', /^\/logements\/\d+$/);
        cy.get('h1').should('contain', 'Maison de Nantes');
        cy.get('.carte-ligne').should('contain', 'Dressing').and('contain', 'Chambre');
        cy.screenshot('inscription-logement');
    });

    it('refuse un email déjà utilisé', () => {
        cy.visit('/inscription');
        cy.get('input[name="inscription[prenom]"]').type('Camille');
        cy.get('input[name="inscription[email]"]').type('camille@dressing.test');
        cy.get('input[name="inscription[plainPassword]"]').type('motdepasse123', { log: false });
        cy.contains('button', 'Continuer').click();

        cy.location('pathname').should('eq', '/inscription');
        cy.get('.erreurs').should('exist');
    });
});
