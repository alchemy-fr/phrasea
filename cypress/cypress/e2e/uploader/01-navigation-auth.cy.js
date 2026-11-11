/**
 * Uploader — authentication, target list, navigation.
 */
import {uploaderUrl} from '../lib/urls';
import {ensureTarget, removeForm, TARGETS} from './lib/api';
import {login, loginThroughKeycloak} from './lib/app';

function switchLanguage(code) {
    // A menu that just closed still holds the pointer events: the trigger
    // would not open it again
    cy.get('[role=menu]').should('not.exist');
    cy.get('body').should('not.have.css', 'pointer-events', 'none');
    cy.getBySel('settings-menu').click();
    cy.getBySel('language-menu').click();
    cy.getBySel(`language-${code}`).click();
}

describe('Uploader navigation & authentication', () => {
    const targets = {};

    before(() => {
        login();
        ensureTarget(TARGETS.plain).then(t => {
            targets.plain = t;
            removeForm(t.id);
        });
        ensureTarget(TARGETS.form).then(t => {
            targets.form = t;
        });
    });

    beforeEach(() => {
        cy.viewport(1400, 900);
    });

    it('sends an anonymous user to Keycloak and back', () => {
        // The test isolation runs before the `before` hook, which logged in
        cy.clearAllCookies();
        cy.clearAllLocalStorage();
        cy.clearAllSessionStorage();
        cy.visit(uploaderUrl);
        loginThroughKeycloak();

        cy.url({timeout: 30000}).should('not.include', '/auth/callback');
        cy.getBySel('topbar').should('be.visible');
        cy.getBySel('user-menu', {timeout: 30000}).should('be.visible');
    });

    it('lists the targets and opens one', () => {
        login();
        cy.visit(uploaderUrl);

        cy.getBySel('page-title', {timeout: 30000}).should('contain', 'Where do you want to upload?');
        cy.contains('[data-testid=target-card]', TARGETS.form.name).should('contain', TARGETS.form.description);
        cy.contains('[data-testid=target-card]', TARGETS.plain.name)
            .should('contain', TARGETS.plain.description)
            .click();

        cy.url().should('include', `/upload/${targets.plain.id}`);
        cy.getBySel('page-title').should('contain', TARGETS.plain.name);
        cy.getBySel('target-description').should('contain', TARGETS.plain.description);
        cy.getBySel('dropzone').should('be.visible');

        cy.getBySel('home-link').click();
        cy.getBySel('target-list').should('be.visible');
    });

    it('opens the admin editors from the top bar', () => {
        login();
        cy.visit(uploaderUrl);

        cy.getBySel('nav-forms').click();
        cy.url().should('include', '/admin/forms');
        cy.getBySel('page-title').should('contain', 'Forms');
        cy.getBySel('nav-forms').should('have.attr', 'aria-current', 'page');

        cy.getBySel('nav-target-data').click();
        cy.url().should('include', '/admin/target-data');
        cy.getBySel('page-title').should('contain', 'Target Data');
        cy.getBySel('nav-target-data').should('have.attr', 'aria-current', 'page');
    });

    it('switches the language from the settings menu', () => {
        login();
        cy.visit(uploaderUrl);
        cy.getBySel('page-title', {timeout: 30000}).should('contain', 'Where do you want to upload?');

        switchLanguage('fr');
        cy.getBySel('page-title').should('contain', 'Où voulez-vous envoyer vos fichiers');

        switchLanguage('en');
        cy.getBySel('page-title').should('contain', 'Where do you want to upload?');
    });

    it('shows a 404 page for unknown routes', () => {
        cy.visit(`${uploaderUrl}/this-route-does-not-exist`, {failOnStatusCode: false});
        cy.contains('404').should('be.visible');
        cy.contains('Page not found').should('be.visible');
    });

    it('signs out', () => {
        login();
        cy.visit(uploaderUrl);
        cy.getBySel('user-menu', {timeout: 30000}).click();
        cy.getBySel('logout').click();

        cy.getBySel('logged-out', {timeout: 30000}).should('be.visible');
        cy.getBySel('sign-in').should('be.visible');
        // The Keycloak session is over: the cached sessions of every app are invalid
        Cypress.session.clearAllSavedSessions();
    });
});
