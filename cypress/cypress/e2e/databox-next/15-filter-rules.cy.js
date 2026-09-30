/**
 * Feature 15 — Attribute filter rules: the assets of a workspace are only
 * visible to the targeted users / groups when they match an AQL condition.
 */
import {deleteWorkspace, ensureUser, seedWorkspace} from './lib/api';
import {expectToastText, login, routeDialog} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

describe('Filter rules', () => {
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
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/filter-rules`);
    });

    it('shows the empty state', () => {
        routeDialog().within(() => {
            cy.contains('Attribute filter rules').should('be.visible');
            cy.contains('No rule').should('be.visible');
        });
    });

    it('creates a rule for a user with an AQL condition', () => {
        routeDialog().within(() => {
            cy.contains('button', 'Add rule').click();
            cy.contains('button', 'Save').should('be.disabled');
            cy.contains('label', 'Users').parent().find('[role=combobox]').click();
        });
        cy.get('[cmdk-input]').type('alice');
        cy.get('[cmdk-item]').contains('alice', {timeout: 20000}).click();
        // Esc in the open picker only closes the picker
        cy.get('[cmdk-input]').type('{esc}');
        routeDialog().within(() => {
            cy.contains('button', 'Add condition').click();
        });
        cy.dialog('last').within(() => {
            cy.contains('[role=tab], button', 'AQL').click();
            cy.getBySel('condition-aql').type('title = "Alpha"');
            cy.contains('button', /^Add$/).click();
        });
        routeDialog().within(() => {
            cy.getBySel('filter-rule-form-condition').should('contain', 'title IS "Alpha"');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Rule saved');
        routeDialog().within(() => {
            cy.getBySel('filter-rule').should('have.length', 1).and('contain', 'alice');
            cy.getBySel('filter-rule-condition').should('contain', 'Alpha');
        });
    });

    it('deletes the rule', () => {
        routeDialog().within(() => {
            cy.getBySel('filter-rule', {timeout: 20000}).should('contain', 'alice');
            cy.getBySel('filter-rule').find('button[aria-label=Delete]').click();
        });
        cy.dialog('last').contains('button', /Confirm|Delete/).click();
        routeDialog().contains('No rule', {timeout: 20000}).should('exist');
    });
});
