/**
 * Feature 2 — Search: full text, URL state, AQL conditions, sort, saved
 * searches, no results.
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {
    assetItem,
    login,
    openLeftPanelTab,
    search,
    visitAssets,
    visitWorkspace,
    waitForResults,
} from './lib/app';

describe('Search', () => {
    let ctx;

    before(() => {
        login();
        seedWorkspace({assets: 4}).then(c => {
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
    });

    it('lists the workspace assets and reflects the query in the URL', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(4);
        cy.getBySel('results-count').should('contain', '4');

        search('Alpha');
        cy.url().should('include', 'q=');
        waitForResults(1);
        assetItem('Alpha').should('exist');

        cy.getBySel('search-clear').click();
        waitForResults(4);
        cy.url().should('not.include', 'q=');
    });

    it('restores the search state from the URL and navigates back', () => {
        visitWorkspace(ctx.workspace.id, {q: 'Bravo'});
        cy.getBySel('search-input').should('have.value', 'Bravo');
        waitForResults(1);
        search('Charlie');
        waitForResults(1);
        assetItem('Charlie').should('exist');
        cy.go('back');
        cy.getBySel('search-input').should('have.value', 'Bravo');
        assetItem('Bravo').should('exist');
    });

    it('shows the no-results state and clears the search', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(4);
        search('zzzz-nothing-matches-zzzz');
        cy.getBySel('no-results', {timeout: 30000}).should('be.visible');
        cy.getBySel('no-results').contains('button', 'Clear search').click();
        cy.url().should('not.include', 'q=');
        waitForResults();
    });

    it('adds, disables and removes an AQL condition', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(4);

        cy.getBySel('add-condition').click();
        cy.dialog().within(() => {
            cy.contains('[role=tab], button', 'AQL').click();
            cy.getBySel('condition-aql').type('keywords = "even"');
            cy.contains('button', /^Add$/).click();
        });
        cy.get('[role=dialog]').should('not.exist');
        cy.getBySel('search-condition').should('have.length', 2); // workspace + keywords
        cy.url().should('include', 'f=');
        waitForResults(2);

        // Disable through the chip menu
        cy.getBySel('search-condition').contains(/keywords/i).closest('[data-testid=search-condition]').as('chip');
        cy.get('@chip').rightclick();
        cy.menuItem('Disable').click();
        cy.get('@chip').should('have.attr', 'data-disabled', 'true');
        waitForResults(4);

        cy.get('@chip').rightclick();
        cy.menuItem('Enable').click();
        waitForResults(2);

        cy.get('@chip').rightclick();
        cy.menuItem('Remove').click();
        cy.getBySel('search-condition').should('have.length', 1);
        waitForResults(4);
    });

    it('edits a condition with the builder', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(4);
        cy.getBySel('add-condition').click();
        cy.dialog('first').within(() => {
            cy.contains('button', 'Select a field').click();
        });
        cy.get('[cmdk-item], [role=option]').contains('Keywords').click();
        cy.dialog('first').within(() => {
            cy.get('input[type=text]').last().type('odd');
            cy.contains('button', /^Add$/).click();
        });
        waitForResults(2);
        assetItem('Alpha').should('exist');
    });

    it('sorts results and groups them by sections', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(4);
        cy.getBySel('sort-button').click();
        cy.get('[role=dialog]').last().within(() => {
            cy.contains('Sort by').should('be.visible');
            cy.get('input[placeholder="Filter…"]').type('Description');
            cy.contains('Description').parent().find('[role=switch]').click();
            cy.contains('button', 'Apply').click();
        });
        cy.url().should('include', 's=');
        waitForResults(4);

        cy.getBySel('sort-button').click();
        cy.get('[role=dialog]').last().contains('button', 'Reset').click();
        cy.url().should('not.include', 's=');
    });

    it('saves, reloads and deletes a saved search', () => {
        const name = `E2E saved ${Date.now()}`;
        visitWorkspace(ctx.workspace.id, {q: 'Delta'});
        waitForResults(1);

        cy.getBySel('search-more').click();
        cy.menuItem('Save search').click();
        cy.dialog().within(() => {
            cy.fieldByLabel('Name').type('{selectAll}{backspace}').should('have.value', '').type(name);
            cy.contains('button', 'Save').click();
        });
        cy.url({timeout: 20000}).should('include', 'id=');

        openLeftPanelTab('Navigation');
        cy.get('input[placeholder="Filter…"]').type('{selectAll}{backspace}').should('have.value', '').type(name);
        cy.getBySel('saved-search-item', {timeout: 20000}).contains(name).scrollIntoView().should('be.visible');

        // Fresh screen, load it from the list
        visitAssets();
        openLeftPanelTab('Navigation');
        cy.get('input[placeholder="Filter…"]').type('{selectAll}{backspace}').should('have.value', '').type(name);
        cy.getBySel('saved-search-item', {timeout: 20000}).contains(name).scrollIntoView().click();
        cy.getBySel('search-input').should('have.value', 'Delta');
        waitForResults(1);

        // Delete from its manage dialog
        cy.getBySel('saved-search-item').contains(name).closest('[data-testid=saved-search-item]').scrollIntoView().find('button').last().click({force: true});
        cy.menuItem('Delete').click();
        cy.dialog().contains('button', /Confirm|Delete/).click();
        cy.getBySel('saved-search-item').contains(name).should('not.exist');
    });

    it('clears the whole search from the "More" menu', () => {
        visitWorkspace(ctx.workspace.id, {q: 'something'});
        cy.getBySel('search-more').click();
        cy.menuItem('Clear search').click();
        cy.url().should('not.include', 'q=').and('not.include', 'f=');
    });
});
