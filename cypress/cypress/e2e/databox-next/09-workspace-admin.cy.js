/**
 * Feature 9 — Workspace administration dialog: info, edit, tags, attribute
 * definitions & policies, renditions, asset policies.
 */
import {deleteWorkspace, ensureUser, seedWorkspace} from './lib/api';
import {dialogTab, expectToastText, login, openLeftPanelTab, routeDialog, treeWorkspace, visitWorkspace, waitForResults} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

const pointer = {button: 0, buttons: 1, isPrimary: true, pointerId: 1, pointerType: 'mouse', force: true};

function center($el) {
    const r = $el[0].getBoundingClientRect();

    return {x: r.left + r.width / 2, y: r.top + r.height / 2};
}

function pointerMove(x, y) {
    cy.get('body').trigger('pointermove', {...pointer, clientX: x, clientY: y, eventConstructor: 'PointerEvent'});
}

/** dnd-kit listens to pointer events: grab the row's handle, move over the target, release */
function dragRow(source, target) {
    source.find('[aria-label=Drag]').then($handle => {
        const from = center($handle);
        cy.wrap($handle).trigger('pointerdown', {...pointer, clientX: from.x, clientY: from.y, eventConstructor: 'PointerEvent'});
        pointerMove(from.x + 10, from.y + 10);
        target.then($target => {
            const to = center($target);
            pointerMove(to.x, to.y);
            cy.wait(100, {log: false});
            pointerMove(to.x + 1, to.y);
        });
        cy.get('body').trigger('pointerup', {...pointer, buttons: 0, eventConstructor: 'PointerEvent'});
    });
}

describe('Workspace administration', () => {
    let ctx;

    before(() => {
        login();
        ensureUser('alice');
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

    it('edits the logo and the terms & conditions', () => {
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/edit`);
        routeDialog().within(() => {
            cy.fieldByLabel('Text').type('Use these assets wisely.');
            cy.getBySel('workspace-logo').find('input[type=file]').selectFile('cypress/fixtures/e2e-image.png', {force: true});
            cy.getBySel('workspace-logo').should('contain', 'e2e-image.png');
            cy.getBySel('workspace-terms-pdf')
                .find('input[type=file]')
                .selectFile({contents: Cypress.Buffer.from('%PDF-1.4\n%%EOF\n'), fileName: 'terms.pdf', mimeType: 'application/pdf'}, {force: true});
            cy.contains('button', 'Save').click();
        });
        expectToastText('Workspace saved');
        cy.reload();
        routeDialog().within(() => {
            cy.fieldByLabel('Text').should('have.value', 'Use these assets wisely.');
            cy.getBySel('workspace-logo').find('img').should('have.attr', 'src');
            cy.getBySel('workspace-terms-pdf').contains('a', 'Current PDF').should('have.attr', 'href');
            // Removed on save
            cy.getBySel('workspace-logo').contains('button', 'Remove the logo').click();
            cy.getBySel('workspace-logo').should('contain', 'The logo will be removed');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Workspace saved');
        routeDialog().getBySel('workspace-logo').find('img').should('not.exist');
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

    it('reorders the attribute definitions by dragging', () => {
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/attributes`);
        routeDialog().getBySel('definition-item').should('have.length', 4);
        cy.getBySel('definition-item').then($items => {
            const first = $items.eq(0).text();
            dragRow(cy.wrap($items.eq(0)), cy.wrap($items.eq(1)));
            cy.getBySel('definition-item').eq(1).should('have.text', first);
            // Saved
            cy.reload();
            routeDialog().getBySel('definition-item').eq(1).should('have.text', first);
        });
    });

    it('manages attribute policies', () => {
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/attribute-policies`);
        routeDialog().within(() => {
            cy.getBySel('definition-item').should('contain', 'Public');
            cy.getBySel('definition-create').click();
            cy.fieldByLabel('Name').type('Business');
            // A private policy cannot be editable by everyone
            cy.getBySel('policy-editable').should('have.attr', 'aria-checked', 'true');
            cy.fieldByLabel('Public').click();
            cy.getBySel('policy-editable').should('have.attr', 'aria-checked', 'false').and('be.disabled');
            cy.fieldByLabel('Public').click();
            cy.getBySel('policy-editable').should('not.be.disabled');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Policy saved');
        routeDialog().getBySel('definition-item').should('contain', 'Business');
    });

    it('creates a private rendition policy', () => {
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/rendition-policies`);
        routeDialog().within(() => {
            cy.getBySel('definition-create').click();
            cy.fieldByLabel('Name').type('Restricted');
            cy.fieldByLabel('Public').click();
            cy.getBySel('policy-editable').should('have.attr', 'aria-checked', 'false').and('be.disabled');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Policy saved');
        routeDialog().within(() => {
            cy.getBySel('definition-item').contains('Restricted').closest('[data-testid=definition-item]').should('contain', 'Private');
            // Not editable by everyone: who may view and edit is granted
            cy.contains('Permissions').should('be.visible');
        });
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
            // Condition: assets of a collection
            cy.contains('button', 'Add condition').click();
            cy.getBySel('asset-policy-condition').should('contain', 'Collection');
            cy.getBySel('asset-policy-condition').contains('Sport').click();
            // Action: hide the attribute definition created above
            cy.contains('button', 'Add action').click();
            cy.getBySel('asset-policy-action').find('[role=combobox]').first().selectOption('Hide attribute');
            cy.getBySel('asset-policy-action').find('[role=combobox]').eq(1).selectOption('Author');
        });
        routeDialog().within(() => {
            cy.getBySel('asset-policy-action').should('have.length', 1).and('contain', 'Author');
            cy.contains('button', 'Save').click();
        });
        expectToastText(/saved/);
    });
});
