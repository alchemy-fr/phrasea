/**
 * Feature 24 — Persisted preferences (server-side user preferences).
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {login, visitWorkspace, waitForResults} from './lib/app';

describe('Persisted preferences', () => {
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
        visitWorkspace(ctx.workspace.id);
        waitForResults(2);
    });

    it('keeps the layout and display options across reloads', () => {
        // Set rather than toggle: a failed run leaves its preferences behind
        const setSwitch = (label, checked) =>
            cy.fieldByLabel(label).then($switch => {
                if (($switch.attr('aria-checked') === 'true') !== checked) {
                    cy.wrap($switch).click();
                }
                cy.fieldByLabel(label).should('have.attr', 'aria-checked', String(checked));
            });

        cy.getBySel('display-options').click();
        cy.get('[role=tab]').contains('List').click();
        setSwitch('Auto play media in preview', true);
        setSwitch('Play media on hover', true);
        cy.get('body').type('{esc}');
        cy.getBySel('asset-list').should('have.attr', 'data-layout', 'list');

        cy.reload();
        cy.getBySel('asset-list', {timeout: 30000}).should('have.attr', 'data-layout', 'list');
        cy.getBySel('display-options').click();
        cy.fieldByLabel('Auto play media in preview').should('have.attr', 'aria-checked', 'true');
        cy.fieldByLabel('Play media on hover').should('have.attr', 'aria-checked', 'true');
        // restore
        setSwitch('Auto play media in preview', false);
        setSwitch('Play media on hover', false);
        cy.get('[role=tab]').contains('Grid').click();
        cy.get('body').type('{esc}');
        cy.getBySel('asset-list').should('have.attr', 'data-layout', 'grid');
    });

    it('keeps the facets configuration', () => {
        cy.getBySel('left-panel').find('[role=tab][aria-label="Facets"]').click();
        cy.getBySel('facet', {timeout: 30000}).should('have.length.greaterThan', 0);
        cy.getBySel('facet').first().find('button').first().click();
        cy.getBySel('facet').first().invoke('attr', 'data-facet').then(name => {
            cy.reload();
            cy.getBySel('facet', {timeout: 30000}).first().should('have.attr', 'data-facet', name);
        });
    });

    it('keeps the left panel tab', () => {
        cy.getBySel('left-panel').find('[role=tab][aria-label="Baskets"]').click();
        cy.reload();
        cy.getBySel('left-panel', {timeout: 30000}).find('[role=tab][aria-selected=true]').should('have.attr', 'aria-label', 'Baskets');
    });
});
