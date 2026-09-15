/**
 * Feature 19 — Operation tasks (admin).
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {expectToastText, login, openSettingsMenu, visitAssets} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

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
    });

    it('runs an "Index assets" task on the workspace', () => {
        cy.visit(`${databoxNextUrl}/admin/tasks`);
        cy.contains('button', 'New task').click();
        cy.contains('Index assets').click();
        cy.fieldByLabel('Workspace').selectOption(ctx.workspace.name);
        cy.contains('button', 'Run').click();
        expectToastText('Task started');
        cy.contains('Index assets', {timeout: 20000}).should('be.visible');
        cy.contains(/Completed|In progress|Pending/, {timeout: 60000}).should('be.visible');
    });

    it('opens the task details', () => {
        cy.visit(`${databoxNextUrl}/admin/tasks`);
        cy.contains('Index assets').first().click();
        cy.contains(/Payload|Output|Started at/).should('be.visible');
    });
});
