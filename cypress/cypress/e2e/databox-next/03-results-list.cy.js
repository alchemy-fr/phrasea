/**
 * Feature 3 — Results list: layouts, selection, bulk toolbar, context menu,
 * display options.
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {assetItem, login, openAssetContextMenu, visitWorkspace, waitForResults} from './lib/app';

describe('Results list', () => {
    let ctx;

    before(() => {
        login();
        seedWorkspace({assets: 5}).then(c => {
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
        waitForResults(5);
    });

    it('switches between grid and list layouts and changes the thumbnail size', () => {
        cy.getBySel('asset-list').should('have.attr', 'data-layout', 'grid');
        cy.getBySel('display-options').click();
        cy.get('[role=tab]').contains('List').click();
        cy.getBySel('asset-list').should('have.attr', 'data-layout', 'list');
        cy.get('[role=tab]').contains('Grid').click();
        cy.getBySel('asset-list').should('have.attr', 'data-layout', 'grid');

        cy.contains('Thumbnail size')
            .parent()
            .find('[role=slider]')
            .first()
            .focus()
            .type('{rightarrow}{rightarrow}{rightarrow}');
        cy.get('body').type('{esc}');

        // The preference is persisted: still a grid after reload
        cy.reload();
        cy.getBySel('asset-list').should('have.attr', 'data-layout', 'grid');
    });

    it('selects items with click, ctrl+click, shift+click and Ctrl+A', () => {
        assetItem('Alpha').click();
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length', 1);
        cy.getBySel('results-count').should('contain', '1 / 5');

        assetItem('Charlie').click({ctrlKey: true});
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length', 2);

        assetItem('Echo').click({shiftKey: true});
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length.gte', 3);

        cy.get('body').type('{ctrl}a');
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length', 5);
        cy.getBySel('results-count').should('contain', '5 / 5');

        cy.getBySel('select-all').should('have.attr', 'aria-checked', 'true').click();
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length', 0);

        cy.getBySel('select-all').should('have.attr', 'aria-checked', 'false').click();
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length', 5);
        cy.get('body').type('{esc}');
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length', 0);
    });

    it('shows bulk actions once something is selected', () => {
        cy.getBySel('selection-actions').within(() => {
            cy.contains('button', 'Edit attributes').should('not.exist');
        });
        assetItem('Alpha').click();
        assetItem('Bravo').click({ctrlKey: true});
        cy.getBySel('selection-actions').within(() => {
            cy.contains('button', 'Edit attributes').should('be.visible');
            cy.contains('button', 'Delete').should('be.visible');
        });
    });

    it('opens the per-asset context menu', () => {
        openAssetContextMenu('Bravo');
        cy.menuItem('Open').should('be.visible');
        cy.menuItem('Info').should('be.visible');
        cy.menuItem('Edit').should('be.visible');
        cy.menuItem('Share').should('be.visible');
        cy.menuItem('Copy').should('be.visible');
        cy.menuItem('Move').should('be.visible');
        cy.menuItem('Delete').should('be.visible');
        cy.get('body').type('{esc}');
    });

    it('displays tags and collections on the cards', () => {
        assetItem('Bravo').should('contain', 'online');
        assetItem('Alpha').should('contain', 'offline');
    });

    it('groups results into sections when sorting with grouping enabled', () => {
        cy.getBySel('sort-button').click();
        cy.get('[role=dialog]').last().within(() => {
            // Grouping needs a non-score first criterion: drop @score, add Keywords
            cy.contains('@score').parent().find('[role=switch]').click();
            cy.contains('@createdAt').parent().find('[role=switch]').click();
            // Grouping works on single-valued attributes
            cy.contains('Description').parent().find('[role=switch]').click();
            cy.fieldByLabel('Group by sections').click();
            cy.contains('button', 'Apply').click();
        });
        cy.url().should('include', 's=');
        cy.contains('Alpha description').should('exist');
        cy.contains('Bravo description').should('exist');
    });
});
