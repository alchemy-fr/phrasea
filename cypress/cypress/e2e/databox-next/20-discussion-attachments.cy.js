/**
 * Feature 20 — Discussion threads and attachments.
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {expectToastText, login} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

describe('Discussion & attachments', () => {
    let ctx;

    before(() => {
        login();
        seedWorkspace({assets: 2}).then(c => {
            ctx = c;
        });
    });

    after(() => {
        if (ctx) {
            deleteWorkspace(ctx.workspace.id);
        }
    });

    beforeEach(() => {
        cy.viewport(1400, 900);
        login();
        cy.visit(`${databoxNextUrl}/assets/${ctx.assets[0].id}/_`);
        cy.getBySel('asset-view', {timeout: 30000}).should('be.visible');
    });

    it('suggests users to mention, out of the panel', () => {
        const admin = Cypress.env('ADMIN_USERNAME');
        cy.getBySel('asset-view').find('textarea[placeholder*="Write a message"]').type(`Hello @${admin.slice(0, 4)}`);
        // Portalled next to the field: the scrolling panel does not clip it
        cy.getBySel('mention-suggestions', {timeout: 20000}).should('be.visible').and('contain', `@${admin}`);
    });

    it('posts, edits and deletes a message', () => {
        cy.getBySel('asset-view').within(() => {
            cy.get('textarea[placeholder*="Write a message"]').type('Hello from Cypress');
            cy.contains('button', 'Send').click();
            cy.contains('Hello from Cypress', {timeout: 20000}).should('be.visible');

            cy.contains('[data-message-id]', 'Hello from Cypress').find('button').last().click({force: true});
        });
        cy.menuItem('Edit').click();
        cy.getBySel('asset-view').within(() => {
            // The thread re-renders while the editor is open, which detaches
            // the textarea: query it again for every step
            cy.get('[data-message-id] textarea').should('have.value', 'Hello from Cypress');
            cy.wait(1000);
            cy.get('[data-message-id] textarea').type('{selectall}{del}Edited from Cypress');
            cy.get('[data-message-id] textarea').should('have.value', 'Edited from Cypress');
            cy.contains('button', 'Save').click();
            cy.contains('Edited from Cypress', {timeout: 20000}).should('be.visible');
            // Let the thread reload settle before opening the row menu
            cy.wait(1500);
            cy.contains('[data-message-id]', 'Edited from Cypress').find('button').last().click({force: true});
        });
        cy.menuItem('Delete').click();
        cy.dialog().contains('button', /Confirm|Delete/).click();
        cy.getBySel('asset-view').contains('Edited from Cypress').should('not.exist');
    });

    it('sends a message with the keyboard shortcut', () => {
        cy.getBySel('asset-view').within(() => {
            cy.get('textarea[placeholder*="Write a message"]').type('Sent with Ctrl+Enter{ctrl}{enter}');
            cy.contains('Sent with Ctrl+Enter', {timeout: 20000}).should('be.visible');
            cy.get('textarea[placeholder*="Write a message"]').should('have.value', '');
        });
    });

    it('attaches another asset and detaches it', () => {
        cy.getBySel('asset-view').within(() => {
            cy.contains('button', 'Attachments').click();
            cy.contains('No attachment').should('be.visible');
            cy.contains('button', 'Add attachment').click();
        });
        cy.dialog().within(() => {
            cy.contains('Add attachment').should('be.visible');
            cy.get('input[placeholder="Name"]').type('E2E attachment');
            cy.get('input[type=file]').selectFile('cypress/fixtures/e2e-image.png', {force: true});
            cy.contains('button', 'Add').click();
        });
        expectToastText('Attachment added');
        cy.getBySel('asset-view').within(() => {
            cy.contains('E2E attachment', {timeout: 30000}).should('be.visible');
            cy.contains('E2E attachment').parents('li, div').first().find('button').last().click({force: true});
        });
        cy.menuItem('Detach').click();
        cy.dialog().contains('button', /Confirm|Detach/).click();
        cy.getBySel('asset-view').contains('E2E attachment').should('not.exist');
    });
});
