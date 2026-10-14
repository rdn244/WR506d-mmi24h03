// Commandes communes aux scénarios de bout en bout

export const MOT_DE_PASSE = 'motdepasse123';

export const COMPTES = {
    camille: 'camille@dressing.test',
    sophie: 'sophie@dressing.test',
    nina: 'nina@dressing.test',
};

// Remet la base de développement dans l'état des fixtures (tâche Node déclarée dans cypress.config.js)
Cypress.Commands.add('chargerFixtures', () => {
    cy.task('chargerFixtures', null, { timeout: 180000 });
});

// Connexion par le vrai formulaire, gardée en cache pour les tests suivants du même compte
Cypress.Commands.add('connexion', (email) => {
    cy.session(email, () => {
        cy.visit('/connexion');
        cy.get('input[name="email"]').type(email);
        cy.get('input[name="password"]').type(MOT_DE_PASSE, { log: false });
        cy.contains('button', 'Se connecter').click();
        cy.location('pathname').should('eq', '/accueil');
    });
});

// La barre de debug Symfony (mode dev) recouvre le bas de l'écran : on la masque pendant les tests
Cypress.on('window:load', (fenetre) => {
    const style = fenetre.document.createElement('style');
    style.textContent = '.sf-toolbar { display: none !important; }';
    fenetre.document.head.appendChild(style);
});
