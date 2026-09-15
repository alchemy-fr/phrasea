/**
 * Feature 16 — CMS pages: back-office list, editor, public rendering.
 */
import {expectToastText, login} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

describe('CMS pages', () => {
    const slug = `e2e-${Date.now()}`;
    const title = `E2E page ${Date.now()}`;

    beforeEach(() => {
        cy.viewport(1400, 900);
    });

    it('creates a page and edits its content', () => {
        login();
        cy.visit(`${databoxNextUrl}/pages`);
        cy.contains('button', 'Create page').click();
        cy.dialog().within(() => {
            cy.fieldByLabel('Title').type(title);
            cy.fieldByLabel('Slug').type(slug);
            cy.fieldByLabel('Public').click();
            cy.contains('button', /Create|Save/).click();
        });
        cy.url({timeout: 20000}).should('include', '/edit');

        cy.get('.ProseMirror, [contenteditable=true]').first().click().type('Hello from Cypress');
        cy.get('[aria-label="Add widget"]').click();
        cy.menuItem('Spacer').click();
        cy.contains('button', 'Save').click();
        expectToastText('Page saved');
    });

    it('lists the page in the back-office', () => {
        login();
        cy.visit(`${databoxNextUrl}/pages`);
        cy.contains(title).should('be.visible');
        cy.contains(title).parents('li').first().contains('Public');
    });

    it('renders the public page', () => {
        cy.visit(`${databoxNextUrl}/p/${slug}`);
        cy.contains('Hello from Cypress', {timeout: 30000}).should('be.visible');
    });

    it('deletes the page', () => {
        login();
        cy.visit(`${databoxNextUrl}/pages`);
        cy.contains(title).parents('li').first().find('button').last().click();
        cy.dialog().within(() => {
            cy.get('input').then($i => {
                if ($i.length) {
                    cy.wrap($i.first()).type(title);
                }
            });
            cy.contains('button', /Confirm|Delete/).click();
        });
        cy.contains(title).should('not.exist');
    });
});
