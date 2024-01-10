import {adminPassword, adminUsername} from "../lib/urls";

describe('Dashboard loads', () => {
    it('passes', () => {
        cy.visit('/');
        cy.contains('Databox');
        cy.contains('Expose');
        cy.contains('Sign In').click();
        cy.get('#username').type(adminUsername);
        cy.get('#password').type(adminPassword);
        cy.contains('Sign In').click();

        cy.get('.MuiAvatar-root').click();
        cy.contains('Logout').click();
        cy.contains('Logout').click();
        cy.contains('Sign In');
    })
})
