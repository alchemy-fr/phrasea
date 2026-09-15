/**
 * Feature 15 — Tag filter rules (per user / group tag inclusion or
 * exclusion). The API only exposes tag rules.
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {expectToastText, login, routeDialog} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

describe('Filter rules', () => {
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
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/filter-rules`);
    });

    it('shows the empty state', () => {
        routeDialog().within(() => {
            cy.contains('Tag filter rules').should('be.visible');
            cy.contains('No rule').should('be.visible');
        });
    });

    it('creates a tag filter rule for a user including a tag', () => {
        routeDialog().within(() => {
            cy.contains('button', 'Add rule').click();
            cy.contains('label', 'User').should('be.visible');
            // A rule targets a user or a group
            cy.contains('label', 'User').parent().find('[role=combobox]').click();
        });
        cy.get('[cmdk-input]').type('alice');
        cy.get('[cmdk-item]').contains('alice', {timeout: 20000}).click();
        cy.get('body').type('{esc}');
        routeDialog().within(() => {
            cy.contains('label', 'Include').parent().find('[role=combobox]').click();
        });
        cy.get('[cmdk-item]').contains('online', {timeout: 20000}).click();
        cy.get('body').type('{esc}');
        routeDialog().within(() => {
            cy.contains('button', 'Save').click();
        });
        expectToastText('Rule saved');
        routeDialog().within(() => {
            cy.contains('alice').should('be.visible');
            cy.contains('online').should('be.visible');
        });
    });

    it('deletes the rule', () => {
        routeDialog().within(() => {
            cy.contains('alice', {timeout: 20000}).should('exist');
            cy.get('button:has(svg.lucide-trash-2), button:has(svg.lucide-trash2)').first().click({force: true});
        });
        cy.dialog('last').contains('button', /Confirm|Delete/).click();
        routeDialog().contains('No rule', {timeout: 20000}).should('exist');
    });
});
