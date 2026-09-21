/**
 * Feature 26 — Organisation theme: an administrator defines a palette and
 * style properties, stored in the stack configuration (`databox.theme`).
 */
import {databoxNextUrl} from '../lib/urls';
import {expectToast, login, openSettingsMenu} from './lib/app';

function removeThemeIfAny() {
    login();
    cy.visit(`${databoxNextUrl}/admin/theme`);
    cy.getBySel('theme-editor', {timeout: 30000}).should('be.visible');
    cy.getBySel('theme-name', {timeout: 30000}).should('be.visible');
    cy.get('body').then($body => {
        if ($body.find('[data-testid=theme-delete]').length) {
            cy.getBySel('theme-delete').click();
            cy.dialog().within(() => {
                cy.contains('button', 'Delete').click();
            });
            expectToast('Theme removed');
        }
    });
}

describe('Organisation theme', () => {
    beforeEach(() => {
        cy.viewport(1400, 900);
        login();
    });

    // Start and leave the stack without an organisation theme
    before(removeThemeIfAny);
    after(removeThemeIfAny);

    it('is reachable from the theme menu', () => {
        cy.visit(`${databoxNextUrl}/assets`);
        openSettingsMenu();
        cy.menuItem('Theme').click();
        cy.menuItem('Customize theme').click();
        cy.url().should('include', '/admin/theme');
        cy.getBySel('theme-editor', {timeout: 30000}).should('be.visible');
    });

    it('saves the theme, previews it live and removes it', () => {
        cy.visit(`${databoxNextUrl}/admin/theme`);
        cy.getBySel('theme-editor', {timeout: 30000}).should('be.visible');
        cy.getBySel('theme-name', {timeout: 20000})
            .should('be.visible')
            .clear()
            .type('Cypress theme');
        // The draft is applied to the page while editing
        cy.getBySel('theme-color-primary')
            .find('input[type=text], input:not([type=color])')
            .first()
            .clear()
            .type('#ff6600');
        cy.get('html')
            .should('have.css', '--primary')
            .and('match', /#ff6600/);
        // A dark alternative, previewed in the dark appearance
        cy.getBySel('theme-tab-dark').click();
        cy.getBySel('theme-dark-enabled').then($switch => {
            if ($switch.attr('aria-checked') !== 'true') {
                cy.wrap($switch).click();
            }
        });
        cy.getBySel('theme-palette-dark').should('be.visible');
        cy.getBySel('theme-preview-dark').click();
        cy.get('html').should('have.class', 'dark');
        cy.getBySel('theme-preview-light').click();
        cy.get('html').should('not.have.class', 'dark');

        cy.getBySel('theme-save').click();
        expectToast('Theme saved');

        cy.reload();
        cy.getBySel('theme-name', {timeout: 30000}).should(
            'have.value',
            'Cypress theme'
        );
        cy.getBySel('theme-tab-dark').click();
        cy.getBySel('theme-dark-enabled').should(
            'have.attr',
            'aria-checked',
            'true'
        );
        // The dark palette pushed the header out of the scrolled area
        cy.getBySel('theme-delete').scrollIntoView().should('be.visible');
        cy.getBySel('theme-delete').click();
        cy.dialog().within(() => {
            cy.contains('button', 'Delete').click();
        });
        expectToast('Theme removed');
        cy.getBySel('theme-delete').should('not.exist');
    });

    it('rejects an invalid palette with a field error', () => {
        cy.visit(`${databoxNextUrl}/admin/theme`);
        cy.getBySel('theme-name', {timeout: 30000})
            .should('be.visible')
            .clear();
        cy.getBySel('theme-save').click();
        cy.contains('The name is required').should('be.visible');
    });
});
