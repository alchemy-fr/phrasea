/**
 * Feature 20 — Discussion threads and attachments.
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {expectToastText, login} from './lib/app';
import {databoxUrl} from '../lib/urls';

const composer = () => cy.getBySel('asset-view').find('[role=textbox][aria-label^="Write a message"]');
const message = (text, options) => cy.getBySel('asset-view').contains('[data-message-id]', text, options);

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
        cy.visit(`${databoxUrl}/assets/${ctx.assets[0].id}/_`);
        cy.getBySel('asset-view', {timeout: 30000}).should('be.visible');
    });

    it('suggests users to mention, out of the panel', () => {
        const admin = Cypress.env('ADMIN_USERNAME');
        composer().type(`Hello @${admin.slice(0, 4)}`);
        // Portalled next to the field: the scrolling panel does not clip it
        cy.getBySel('mention-suggestions', {timeout: 20000}).should('be.visible').and('contain', `@${admin}`);
    });

    it('posts, edits and deletes a message', () => {
        composer().type('Hello from Cypress');
        cy.getBySel('asset-view').find('button[aria-label="Send"]').click();
        message('Hello from Cypress', {timeout: 20000}).should('be.visible');
        composer().should('have.text', '');

        message('Hello from Cypress').find('button').last().click({force: true});
        cy.menuItem('Edit').click();
        // The message is edited in the composer
        composer().should('have.text', 'Hello from Cypress');
        // The thread re-renders once the edition starts, which resets the
        // composer content: let it settle before typing
        cy.wait(1000);
        composer().type('{selectall}{del}Edited from Cypress');
        composer().should('have.text', 'Edited from Cypress');
        cy.getBySel('asset-view').find('button[aria-label="Save"]').click();
        message('Edited from Cypress', {timeout: 20000}).should('be.visible');
        // Let the thread reload settle before opening the row menu
        cy.wait(1500);

        message('Edited from Cypress').find('button').last().click({force: true});
        cy.menuItem('Delete').click();
        cy.dialog().contains('button', /Confirm|Delete/).click();
        cy.getBySel('asset-view').find('[data-message-id]').should('not.exist');
    });

    it('sends a message with the keyboard shortcut', () => {
        composer().type('Sent with Ctrl+Enter{ctrl}{enter}');
        message('Sent with Ctrl+Enter', {timeout: 20000}).should('be.visible');
        composer().should('have.text', '');
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
