/**
 * Feature 22 — User preferences: theme, UI language, data locale.
 */
import {login, openSettingsMenu, visitAssets} from './lib/app';

describe('Preferences', () => {
    beforeEach(() => {
        cy.viewport(1400, 900);
        login();
        visitAssets();
    });

    it('switches the theme and persists it', () => {
        openSettingsMenu();
        cy.menuItem('Theme').click();
        cy.menuItem('Dark').click();
        cy.get('html').should('have.class', 'dark');
        cy.reload();
        cy.get('html', {timeout: 20000}).should('have.class', 'dark');

        openSettingsMenu();
        cy.menuItem('Theme').click();
        cy.menuItem('Light').click();
        cy.get('html').should('not.have.class', 'dark');
    });

    it('switches the interface language', () => {
        openSettingsMenu();
        cy.menuItem('Language').click();
        cy.menuItem('Français').click();
        cy.getBySel('search-submit', {timeout: 20000}).should('contain', 'Rechercher');
        cy.getCookie('dbx_lang').should('have.property', 'value', 'fr');
        cy.reload();
        cy.getBySel('search-submit', {timeout: 20000}).should('contain', 'Rechercher');

        openSettingsMenu();
        cy.menuItem('Langue').click();
        cy.menuItem('English').click();
        cy.getBySel('search-submit', {timeout: 20000}).should('contain', 'Search');
    });

    it('opens the data language dialog', () => {
        openSettingsMenu();
        cy.menuItem('Language').click();
        cy.menuItem('Data language').click();
        cy.dialog().within(() => {
            cy.contains('Data language').should('be.visible');
            cy.contains('button', 'Cancel').click();
        });
    });
});
