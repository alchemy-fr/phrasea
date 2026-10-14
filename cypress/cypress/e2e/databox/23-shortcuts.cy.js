/**
 * Feature 23 — Keyboard shortcuts.
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {assetItem, login, visitWorkspace, waitForResults} from './lib/app';

describe('Keyboard shortcuts', () => {
    let ctx;

    before(() => {
        login();
        seedWorkspace({assets: 3}).then(c => {
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
        visitWorkspace(ctx.workspace.id);
        waitForResults(3);
    });

    it('Ctrl+A selects every result, Escape clears the selection', () => {
        // The search input is autofocused: give the focus to the list first
        cy.getBySel('asset-list').click('bottomRight');
        cy.get('body').type('{ctrl}a');
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length', 3);
        cy.get('body').type('{esc}');
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length', 0);
    });

    it('Ctrl+A inside the search input does not select the results', () => {
        cy.getBySel('search-input').type('abc{ctrl}a');
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length', 0);
        cy.getBySel('search-input').invoke('val').should('include', 'abc');
    });

    it('Enter in the search input submits the query', () => {
        cy.getBySel('search-input').type('Bravo{enter}');
        cy.url().should('include', 'q=');
        waitForResults(1);
    });

    it('arrow keys and Escape drive the viewer', () => {
        cy.getBySel('asset-item').first().dblclick();
        cy.getBySel('asset-view', {timeout: 30000}).should('be.visible');
        cy.getBySel('asset-view-title').invoke('text').then(first => {
            cy.get('body').type('{rightarrow}');
            cy.getBySel('asset-view-title').should('not.have.text', first);
        });
        cy.get('body').type('{esc}');
        cy.getBySel('asset-view').should('not.exist');
    });
});
