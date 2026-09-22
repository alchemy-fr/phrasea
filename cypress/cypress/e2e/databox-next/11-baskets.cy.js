/**
 * Feature 11 — Baskets.
 */
import {apiRequest, deleteWorkspace, seedWorkspace, waitForBasketListed} from './lib/api';
import {assetItem, dialogTab, expectToastText, login, openAssetContextMenu, openLeftPanelTab, routeDialog, visitWorkspace, waitForResults} from './lib/app';

/**
 * Creating a basket already makes it the current one, and the row then offers
 * "Unset as current" instead.
 */
function makeCurrent(basketName) {
    cy.getBySel('basket-item')
        .contains(basketName)
        .closest('[data-testid=basket-item]')
        .then($row => {
            if ($row.attr('data-current') === 'true') {
                return;
            }

            cy.wrap($row).rightclick();
            cy.menuItem('Set as current').click();
        });
}

describe('Baskets', () => {
    let ctx;
    const basketName = `E2E basket ${Date.now()}`;

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

    it('creates a basket from the baskets panel', () => {
        openLeftPanelTab('Baskets');
        cy.get('[aria-label="Create basket"]').click();
        cy.dialog().within(() => {
            cy.fieldByLabel('Name').type(basketName);
            cy.fieldByLabel('Description').type('Created by Cypress');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Basket created');
        cy.getBySel('basket-item').contains(basketName).should('be.visible');
        // The panel only refetches on load: the next specs need it indexed
        waitForBasketListed(basketName);
    });

    it('sets the basket as current and adds a selection to it', () => {
        openLeftPanelTab('Baskets');
        makeCurrent(basketName);
        cy.getBySel('basket-item').contains(basketName).closest('[data-testid=basket-item]').should('have.attr', 'data-current', 'true');

        assetItem('Alpha').click();
        assetItem('Bravo').click({ctrlKey: true});
        cy.getBySel('selection-actions').contains('button', basketName).click();
        expectToastText('2 item(s) added to basket');
    });

    it('adds an asset from the context menu and opens the basket view', () => {
        openLeftPanelTab('Baskets');
        makeCurrent(basketName);
        openAssetContextMenu('Charlie');
        cy.menuItem('Add to basket').click();
        expectToastText('1 item(s) added to basket');

        openLeftPanelTab('Baskets');
        cy.getBySel('basket-item').contains(basketName).click();
        cy.getBySel('basket-view', {timeout: 30000}).within(() => {
            cy.contains(basketName).should('be.visible');
            cy.contains('3 item(s)').should('be.visible');
            cy.getBySel('asset-item', {timeout: 30000}).should('have.length', 3);
        });
    });

    it('removes an item from the basket', () => {
        openLeftPanelTab('Baskets');
        cy.getBySel('basket-item').contains(basketName).click();
        cy.getBySel('basket-view', {timeout: 30000}).within(() => {
            cy.getBySel('asset-item', {timeout: 30000}).contains('Charlie').closest('[data-testid=asset-item]').click();
            cy.contains('button', 'Remove from basket').click();
        });
        expectToastText('1 item(s) removed from basket');
        cy.getBySel('basket-view').findBySel('asset-item').should('have.length', 2);
    });

    it('closes the basket view for the page it was opened from', () => {
        const other = `${basketName} bis`;
        apiRequest({method: 'POST', path: '/baskets', body: {name: other}});
        waitForBasketListed(other);

        openLeftPanelTab('Baskets');
        cy.getBySel('basket-item').contains(basketName).click();
        cy.getBySel('basket-view', {timeout: 30000}).should('be.visible');

        // Switching basket from inside the view is the same screen…
        cy.getBySel('basket-view').findBySel('basket-item').contains(other).click();
        cy.getBySel('basket-view', {timeout: 30000}).contains(other).should('be.visible');

        // …so closing leaves for the search screen, not for the first basket
        cy.getBySel('basket-view').find('[aria-label=Close]').click();
        cy.getBySel('basket-view').should('not.exist');
        cy.getBySel('search-input').should('be.visible');
        cy.url().should('include', '/assets');
    });

    it('offers the display options of the results in the basket view', () => {
        openLeftPanelTab('Baskets');
        cy.getBySel('basket-item').contains(basketName).click();
        cy.getBySel('basket-view', {timeout: 30000}).should('be.visible');

        cy.getBySel('basket-view').findBySel('display-options').click();
        cy.contains('Thumbnail size').should('be.visible');
        cy.contains('[role=tab]', 'List').click();
        cy.get('body').type('{esc}');
        cy.getBySel('asset-list').should('have.attr', 'data-layout', 'list');

        // Back to the grid: the preference is shared with the search screen
        cy.getBySel('basket-view').findBySel('display-options').click();
        cy.contains('[role=tab]', 'Grid').click();
        cy.get('body').type('{esc}');
        cy.getBySel('asset-list').should('have.attr', 'data-layout', 'grid');
    });

    it('reaches the basket actions from the "…" of the switcher', () => {
        openLeftPanelTab('Baskets');
        makeCurrent(basketName);

        cy.get('[aria-label="Switch basket"]').click();
        cy.contains('[data-testid=basket-switch-row]', basketName)
            .findBySel('basket-switch-more')
            .click();
        cy.menuItem('Delete').should('be.visible');
        cy.menuItem('Edit').click();
        cy.dialog().within(() => {
            cy.fieldByLabel('Name').should('have.value', basketName);
            cy.contains('button', 'Cancel').click();
        });
    });

    it('edits, archives, unarchives and deletes the basket', () => {
        openLeftPanelTab('Baskets');
        cy.getBySel('basket-item').contains(basketName).closest('[data-testid=basket-item]').rightclick();
        cy.menuItem('Edit').click();
        // "Edit" opens the routed manage dialog on its edit tab
        dialogTab('Edit');
        routeDialog().within(() => {
            cy.fieldByLabel('Name').type('{selectAll}{backspace}').should('have.value', '').type(`${basketName} edited`);
            cy.contains('button', 'Save').click();
        });
        expectToastText('Basket updated');
        cy.get('body').type('{esc}');
        openLeftPanelTab('Baskets');

        cy.getBySel('basket-item').contains(`${basketName} edited`).closest('[data-testid=basket-item]').rightclick();
        cy.menuItem('Archive').click();
        cy.contains('[data-testid=basket-item]', `${basketName} edited`).should('not.exist');
        cy.fieldByLabel('Display archived').click();
        cy.getBySel('basket-item').contains(`${basketName} edited`).should('be.visible');
        cy.getBySel('basket-item').contains(`${basketName} edited`).closest('[data-testid=basket-item]').rightclick();
        cy.menuItem('Unarchive').click();

        cy.getBySel('basket-item').contains(`${basketName} edited`).closest('[data-testid=basket-item]').rightclick();
        cy.menuItem('Delete').click();
        // Queried from the root, not chained on the dialog: the confirmation
        // is remounted while the basket list settles behind it, and a
        // `contains()` chained on a captured element cannot requery
        cy.contains('[role=dialog] button', /Delete|Confirm/)
            .should('be.visible')
            .click();
        cy.contains('[data-testid=basket-item]', `${basketName} edited`).should('not.exist');
    });
});
