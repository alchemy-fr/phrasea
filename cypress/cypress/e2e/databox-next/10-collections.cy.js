/**
 * Feature 10 — Collections tree and collection management.
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {dialogTab, expandTreePickerWorkspace, expandTreeWorkspace, expectToastText, login, openLeftPanelTab, routeDialog, treeCollection, treeWorkspace, visitWorkspace, waitForResults} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

describe('Collections', () => {
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
        visitWorkspace(ctx.workspace.id);
        waitForResults(4);
        openLeftPanelTab('Navigation');
    });

    it('expands the tree and filters the results by collection', () => {
        expandTreeWorkspace(ctx.workspace.id);
        treeCollection(ctx.sport.id).scrollIntoView().should('be.visible');
        treeCollection(ctx.entertainment.id).should('exist');
        treeCollection(ctx.sport.id).find('[aria-label=Expand]').click();
        treeCollection(ctx.football.id).scrollIntoView().should('be.visible');

        treeCollection(ctx.football.id).contains('Football').click();
        cy.url().should('include', 'collection');
        waitForResults(2);
        treeCollection(ctx.football.id).should('have.attr', 'data-selected', 'true');

        treeWorkspace(ctx.workspace.id).contains(ctx.workspace.name).click();
        waitForResults(4);
    });

    it('searches collections by name', () => {
        // Root collections are searchable by name
        cy.get('input[placeholder="Search collections…"]').type('Entertainment');
        cy.getBySel('collection-search-result', {timeout: 20000}).contains('Entertainment').first().click();
        cy.url().should('include', 'collection');
    });

    it('creates a sub-collection from the context menu', () => {
        expandTreeWorkspace(ctx.workspace.id);
        treeCollection(ctx.entertainment.id).rightclick();
        cy.menuItem('Create sub-collection').click();
        cy.dialog().within(() => {
            cy.contains('New collection').should('be.visible');
            cy.fieldByLabel('Name').type('Archives');
            cy.contains('button', 'Create').click();
        });
        expectToastText('Collection created');
        treeCollection(ctx.entertainment.id).find('[aria-label=Expand]').click();
        cy.getBySel('collection-item').contains('Archives').should('be.visible');
    });

    it('renames a collection through its manage dialog', () => {
        cy.visit(`${databoxNextUrl}/collections/${ctx.sport.id}/manage/edit`);
        routeDialog().within(() => {
            cy.contains('Manage collection').should('be.visible');
            cy.fieldByLabel('Name').type('{selectAll}{backspace}').should('have.value', '').type('Sports');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Collection saved');
        dialogTab('Info');
        routeDialog().contains('Sports').should('be.visible');
    });

    it('moves a collection to another parent', () => {
        cy.visit(`${databoxNextUrl}/collections/${ctx.football.id}/manage/operations`);
        routeDialog().within(() => {
            cy.contains('Move collection').should('be.visible');
            expandTreePickerWorkspace(ctx.workspace.name);
        });
        routeDialog().within(() => {
            cy.get('[data-testid=tree-picker-collection]').contains('Entertainment').click();
            cy.contains('button', 'Move').click();
        });
        expectToastText('Collection moved');
    });

    it('toggles notifications of a collection', () => {
        cy.visit(`${databoxNextUrl}/collections/${ctx.entertainment.id}/manage/notifications`);
        routeDialog().within(() => {
            cy.contains('Receive a notification').should('be.visible');
            cy.contains('button', 'Follow').click();
            cy.contains('button', 'Unfollow', {timeout: 20000}).should('be.visible').click();
            cy.contains('button', 'Follow', {timeout: 20000}).should('be.visible');
        });
    });

    it('deletes a collection to the trash and restores it', () => {
        expandTreeWorkspace(ctx.workspace.id);
        treeCollection(ctx.entertainment.id).rightclick();
        cy.menuItem('Delete').click();
        cy.dialog().within(() => {
            cy.contains('Delete collection').should('be.visible');
            // Destructive action: the collection name must be typed
            cy.get('input').first().type('Entertainment');
            cy.contains('button', /Delete|Confirm/).click();
        });
        expectToastText('Collection moved to trash');

        cy.visit(`${databoxNextUrl}/collections/${ctx.entertainment.id}/manage/operations`);
        routeDialog().within(() => {
            cy.contains('button', 'Restore').click();
        });
        cy.dialog('last').contains('button', /Restore|Confirm/).click();
        expectToastText('Collection restored');
    });
});
