/**
 * Feature 9 — Workspace administration dialog: info, edit, tags, attribute
 * definitions & policies, renditions, asset policies.
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {dialogTab, expectToastText, login, openLeftPanelTab, routeDialog, treeWorkspace, visitWorkspace, waitForResults} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

describe('Workspace administration', () => {
    let ctx;

    before(() => {
        login();
        seedWorkspace({assets: 1}).then(c => {
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

    it('opens the manage dialog from the tree and lists its tabs', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(1);
        openLeftPanelTab('Navigation');
        treeWorkspace(ctx.workspace.id).rightclick();
        cy.menuItem('Manage workspace').click();
        routeDialog().within(() => {
            cy.contains('Manage workspace').should('be.visible');
            ['Info', 'Edit', 'Permissions', 'Tags', 'Entities', 'Attributes', 'Renditions', 'Integrations', 'Filter rules'].forEach(tab => {
                cy.get('[role=tab]').contains(tab).should('exist');
            });
        });
    });

    it('edits the workspace title and locales', () => {
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/edit`);
        routeDialog().within(() => {
            cy.fieldByLabel('Title').type('{selectAll}{backspace}').should('have.value', '').type(`${ctx.workspace.name} edited`);
            cy.contains('Enabled locales').should('be.visible');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Workspace saved');
        dialogTab('Info');
        routeDialog().contains(`${ctx.workspace.name} edited`).should('exist');
    });

    it('creates and deletes a tag', () => {
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/tags`);
        routeDialog().within(() => {
            cy.getBySel('definition-item').should('have.length', 2);
            cy.getBySel('definition-create').click();
            cy.fieldByLabel('Name').type('embargo');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Tag saved');
        routeDialog().within(() => {
            cy.getBySel('definition-item').should('have.length', 3);
            cy.getBySel('definition-item').contains('embargo').closest('[data-testid=definition-item]').find('[aria-label=Delete]').click({force: true});
        });
        cy.dialog('last').contains('button', /Confirm|Delete/).click();
        routeDialog().getBySel('definition-item').should('have.length', 2);
    });

    it('creates an attribute definition', () => {
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/attributes`);
        routeDialog().within(() => {
            cy.getBySel('definition-item').should('have.length', 3);
            cy.getBySel('definition-create').click();
            cy.fieldByLabel('Name').type('Author');
            cy.fieldByLabel('Policy').selectOption('Public');
            cy.fieldByLabel('Facet').click();
            cy.contains('button', 'Save').click();
        });
        expectToastText('Attribute saved');
        routeDialog().getBySel('definition-item').should('have.length', 4).and('contain', 'Author');
    });

    it('manages attribute policies', () => {
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/attribute-policies`);
        routeDialog().within(() => {
            cy.getBySel('definition-item').should('contain', 'Public');
            cy.getBySel('definition-create').click();
            cy.fieldByLabel('Name').type('Business');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Policy saved');
        routeDialog().getBySel('definition-item').should('contain', 'Business');
    });

    it('creates a rendition definition', () => {
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/renditions`);
        routeDialog().within(() => {
            cy.getBySel('definition-create').click();
            cy.fieldByLabel('Name').type('thumbnail-e2e');
            cy.fieldByLabel('Policy').selectOption('Public');
            cy.fieldByLabel('Build mode').selectOption('No automatic build');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Rendition definition saved');
        routeDialog().getBySel('definition-item').should('contain', 'thumbnail-e2e');
    });

    it('creates an asset policy', () => {
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/asset-policies`);
        routeDialog().within(() => {
            cy.getBySel('definition-create').click();
            cy.fieldByLabel('Name').type('Hide for guests');
            cy.contains('Conditions').should('be.visible');
            cy.contains('label', 'Target users').parent().find('[role=combobox]').click();
        });
        cy.get('[cmdk-input]').type('alice');
        cy.get('[cmdk-item]').contains('alice', {timeout: 20000}).click();
        cy.get('body').type('{esc}');
        routeDialog().within(() => {
            cy.contains('button', 'Add action').click();
        });
        // Action row: type then target attribute slug
        routeDialog().find('[role=combobox]').filter(':visible').last().selectOption('Hide attribute');
        routeDialog().within(() => {
            cy.get('input[placeholder="attribute slug"]').type('description');
            cy.contains('button', 'Save').click();
        });
        expectToastText(/saved/);
    });
});
