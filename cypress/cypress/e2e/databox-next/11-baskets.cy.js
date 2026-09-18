/**
 * Feature 11 — Baskets.
 */
import {deleteWorkspace, seedWorkspace, waitForBasketListed} from './lib/api';
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
        cy.dialog().contains('button', /Delete|Confirm/).click();
        cy.contains('[data-testid=basket-item]', `${basketName} edited`).should('not.exist');
    });
});
