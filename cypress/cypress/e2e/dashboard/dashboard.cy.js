import {adminPassword, adminUsername, keycloakUrl} from '../lib/urls';

describe('Dashboard loads', () => {
    it('passes', () => {
        cy.visit('/');
        cy.getBySel('service-databox').should('contain', 'Databox');
        cy.getBySel('service-expose').should('contain', 'Expose');
        cy.getBySel('sign-in').click();

        cy.origin(
            keycloakUrl,
            {
                args: {
                    adminUsername,
                    adminPassword,
                },
            },
            ({adminUsername, adminPassword}) => {
                cy.get('#username').type(adminUsername);
                cy.get('#password').type(adminPassword);
                cy.contains('Sign In').click();
            }
        );

        cy.getBySel('user-menu', {timeout: 30000}).click();
        cy.getBySel('logout').click();
        cy.getBySel('sign-in', {timeout: 30000}).should('be.visible');
    });
});
