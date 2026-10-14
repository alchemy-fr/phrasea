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

    it('offers preset themes independently of the appearance', () => {
        openSettingsMenu();
        cy.menuItem('Theme').click();
        cy.menuItem('Ocean').click();
        cy.get('html').should('have.attr', 'data-theme', 'ocean');
        cy.reload();
        cy.get('html', {timeout: 20000}).should(
            'have.attr',
            'data-theme',
            'ocean'
        );
        // The menu only responds once the app is hydrated: the user menu is
        // rendered after the session is restored client side
        cy.getBySel('user-menu', {timeout: 30000}).should('exist');

        // The appearance is a separate choice: the preset keeps its identity in dark
        openSettingsMenu();
        cy.menuItem('Theme').click();
        cy.menuItem('Dark').click();
        cy.get('html')
            .should('have.attr', 'data-theme', 'ocean')
            .and('have.class', 'dark');

        // Reopening the menu right after a selection is flaky (close
        // animation): start afresh
        cy.reload();
        cy.getBySel('user-menu', {timeout: 30000}).should('exist');
        openSettingsMenu();
        cy.menuItem('Theme').click();
        cy.menuItem('Light').click();
        cy.get('html').should('not.have.class', 'dark');

        cy.reload();
        cy.getBySel('user-menu', {timeout: 30000}).should('exist');
        openSettingsMenu();
        cy.menuItem('Theme').click();
        cy.getBySel('theme-default').click();
        cy.get('html').should('not.have.attr', 'data-theme');
    });

    it('switches the interface language', () => {
        openSettingsMenu();
        cy.menuItem('Language').click();
        cy.menuItem('Français').click();
        cy.getBySel('search-submit', {timeout: 20000}).should(
            'contain',
            'Rechercher'
        );
        cy.getCookie('dbx_lang').should('have.property', 'value', 'fr');
        cy.reload();
        cy.getBySel('search-submit', {timeout: 20000}).should(
            'contain',
            'Rechercher'
        );

        openSettingsMenu();
        cy.menuItem('Langue').click();
        cy.menuItem('English').click();
        cy.getBySel('search-submit', {timeout: 20000}).should(
            'contain',
            'Search'
        );
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
