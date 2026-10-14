const { execSync } = require('node:child_process');
const { defineConfig } = require('cypress');

// Tests de bout en bout : make e2e (sans fenêtre) ou make e2e-ouvrir (démonstration)
module.exports = defineConfig({
    e2e: {
        baseUrl: 'http://localhost:8080',
        // Application pensée mobile d'abord : écran de téléphone
        viewportWidth: 390,
        viewportHeight: 844,
        specPattern: 'cypress/e2e/**/*.cy.js',
        supportFile: 'cypress/support/e2e.js',
        video: false,
        screenshotOnRunFailure: true,
        defaultCommandTimeout: 8000,
        setupNodeEvents(on) {
            on('task', {
                // Remet la base de développement dans l'état des fixtures (src/DataFixtures)
                chargerFixtures() {
                    execSync('make fixtures', { cwd: __dirname, stdio: 'pipe', timeout: 180000 });

                    return null;
                },
            });
        },
    },
});
