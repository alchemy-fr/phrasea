/**
 * Feature 19 — Operation tasks (admin).
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {expectToastText, login, openSettingsMenu, visitAssets} from './lib/app';
import {databoxUrl} from '../lib/urls';

const taskDetailsUrl = /\/admin\/tasks\/([0-9a-f-]{36})$/;

describe('Operation tasks', () => {
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

    it('is reachable from the settings menu', () => {
        visitAssets();
        openSettingsMenu();
        cy.menuItem('Operation tasks').click();
        cy.url().should('include', '/admin/tasks');
        cy.contains('Operation tasks').should('be.visible');
        // Where the user is, and the way back to the assets
        cy.getBySel('page-title').should('have.text', 'Operation tasks');
        cy.getBySel('trail-root').should('be.visible');
    });

    it('runs an "Index assets" task on the workspace', () => {
        cy.visit(`${databoxUrl}/admin/tasks`);
        cy.contains('a', 'New task').click();
        cy.url().should('match', /\/admin\/tasks\/new$/);
        cy.getBySel('task-catalog-intro')
            .should('contain.text', 'You are about to start a new task');
        cy.contains('Index assets').click();
        cy.url().should('match', /\/admin\/tasks\/new\/index_assets$/);
        cy.fieldByLabel('Workspace').selectOption(ctx.workspace.name);
        cy.contains('button', 'Run').click();
        expectToastText('Task started');
        // The details of the created task
        cy.url({timeout: 20000}).should('match', taskDetailsUrl);
        cy.contains('Index assets', {timeout: 20000}).should('be.visible');
        cy.contains(/Completed|In progress|Pending/, {timeout: 60000}).should('be.visible');
    });

    it('opens the task details', () => {
        cy.visit(`${databoxUrl}/admin/tasks`);
        cy.getBySel('task-row').contains('Index assets').first().click();
        cy.url().should('match', taskDetailsUrl);
        cy.contains(/Payload|Output|Started at/).should('be.visible');
        // The details stay on reload
        cy.reload();
        cy.url().should('match', taskDetailsUrl);
        cy.contains('Payload', {timeout: 20000}).should('be.visible');
    });

    it('deletes a completed task', () => {
        cy.visit(`${databoxUrl}/admin/tasks`);
        cy.getBySel('task-row').contains('Index assets').first().click();
        cy.url().should('match', taskDetailsUrl).then(url => {
            const id = url.match(taskDetailsUrl)[1];
            cy.contains('Completed', {timeout: 60000}).should('be.visible');
            cy.getBySel('task-delete').click();
            cy.get('[role=dialog]').contains('button', 'Delete').click();
            expectToastText('Task deleted');
            cy.url().should('match', /\/admin\/tasks$/);
            cy.get(`a[href$="/admin/tasks/${id}"]`).should('not.exist');
        });
    });
});
