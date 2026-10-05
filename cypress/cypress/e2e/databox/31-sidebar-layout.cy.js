/**
 * Feature 31 — Sidebar layout: order of the "Browse" sections, pinning a
 * workspace as a saved search, width of the left panel and of the quarantine
 * queue.
 */
import {apiRequest, deleteWorkspace, seedWorkspace} from './lib/api';
import {
    assetsUrl,
    login,
    openLeftPanelTab,
    treeWorkspace,
    visitAssets,
    visitWorkspace,
    waitForResults,
} from './lib/app';
import {databoxUrl} from '../lib/urls';

const sectionOrder = expected =>
    cy.getBySel('sortable-section').should($sections => {
        expect([...$sections].map(s => s.dataset.sectionId)).to.deep.equal(
            expected
        );
    });

const grip = section =>
    cy.get(
        `[data-testid=sortable-section][data-section-id=${section}] [data-testid=section-grip]`
    );

const members = res => res['hydra:member'] ?? res.member ?? [];

describe('Sidebar layout', () => {
    let ctx;

    before(() => {
        login();
        seedWorkspace({assets: 2}).then(c => {
            ctx = c;
        });
    });

    after(() => {
        if (ctx) {
            apiRequest({
                path: '/saved-searches',
                qs: {query: ctx.workspace.name},
            }).then(res =>
                members(res).forEach(s =>
                    apiRequest({
                        method: 'DELETE',
                        path: `/saved-searches/${s.id}`,
                    })
                )
            );
            deleteWorkspace(ctx.workspace.id);
        }
        apiRequest({
            method: 'PUT',
            path: '/preferences',
            body: {name: 'sidebarSections', value: null},
        });
    });

    beforeEach(() => {
        cy.viewport(1400, 900);
        login();
    });

    it('reorders the sections of the Browse tab and keeps the order', () => {
        apiRequest({
            method: 'PUT',
            path: '/preferences',
            body: {name: 'sidebarSections', value: null},
        });
        visitWorkspace(ctx.workspace.id);
        waitForResults(2);
        openLeftPanelTab('Navigation');
        sectionOrder(['pinnedStories', 'savedSearches', 'collections']);

        // With the keyboard: the grip keeps the focus as its section moves
        grip('collections').focus().type('{uparrow}');
        sectionOrder(['pinnedStories', 'collections', 'savedSearches']);
        cy.focused()
            .should('have.attr', 'data-testid', 'section-grip')
            .type('{uparrow}');
        sectionOrder(['collections', 'pinnedStories', 'savedSearches']);

        // By dragging the grip below the last section
        cy.get(
            '[data-testid=sortable-section][data-section-id=savedSearches]'
        ).then($last => {
            const {bottom} = $last[0].getBoundingClientRect();
            grip('collections').then($grip => {
                const {left, top} = $grip[0].getBoundingClientRect();
                cy.wrap($grip).trigger('pointerdown', {
                    button: 0,
                    pointerId: 1,
                    clientX: left + 4,
                    clientY: top + 4,
                });
                cy.document().trigger('pointermove', {
                    pointerId: 1,
                    clientX: left + 4,
                    clientY: bottom - 4,
                });
                cy.document().trigger('pointerup', {
                    pointerId: 1,
                    clientX: left + 4,
                    clientY: bottom - 4,
                });
            });
        });
        sectionOrder(['pinnedStories', 'savedSearches', 'collections']);
        grip('collections').focus().type('{uparrow}');
        sectionOrder(['pinnedStories', 'collections', 'savedSearches']);

        // Saved in the preferences
        cy.reload();
        openLeftPanelTab('Navigation');
        sectionOrder(['pinnedStories', 'collections', 'savedSearches']);
    });

    it('pins a workspace as a saved search', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(2);
        openLeftPanelTab('Navigation');
        treeWorkspace(ctx.workspace.id).rightclick();
        cy.menuItem('Pin').click();
        cy.contains(
            `"${ctx.workspace.name}" pinned to the saved searches`
        ).should('be.visible');

        // Opened from a fresh, unfiltered screen: it filters on the workspace
        visitAssets();
        openLeftPanelTab('Navigation');
        cy.get('input[placeholder="Filter…"]').type(ctx.workspace.name);
        cy.contains('[data-testid=saved-search-item]', ctx.workspace.name, {
            timeout: 20000,
        })
            .scrollIntoView()
            .click();
        cy.url().should('include', ctx.workspace.id);
        waitForResults(2);
    });

    it('resizes the left panel and keeps its width', () => {
        visitAssets();
        cy.getBySel('left-panel-aside').invoke('outerWidth').should('eq', 300);
        cy.getBySel('left-panel-resize')
            .focus()
            .type('{rightarrow}{rightarrow}');
        cy.getBySel('left-panel-aside').invoke('outerWidth').should('eq', 340);

        cy.getBySel('left-panel-resize').then($handle => {
            const {left, top} = $handle[0].getBoundingClientRect();
            cy.wrap($handle).trigger('pointerdown', {
                button: 0,
                pointerId: 1,
                clientX: left,
                clientY: top + 100,
            });
            cy.document().trigger('pointermove', {
                pointerId: 1,
                clientX: left + 60,
                clientY: top + 100,
            });
            cy.document().trigger('pointerup', {
                pointerId: 1,
                clientX: left + 60,
                clientY: top + 100,
            });
        });
        cy.getBySel('left-panel-aside').invoke('outerWidth').should('eq', 400);

        cy.reload();
        cy.getBySel('left-panel-aside', {timeout: 30000})
            .invoke('outerWidth')
            .should('eq', 400);
    });

    it('resizes the quarantine queue', () => {
        cy.visit(`${databoxUrl}/quarantine`);
        cy.getBySel('quarantine-queue', {timeout: 30000})
            .invoke('outerWidth')
            .should('eq', 280);
        cy.getBySel('quarantine-queue-resize').focus().type('{leftarrow}');
        cy.getBySel('quarantine-queue').invoke('outerWidth').should('eq', 260);
        cy.getBySel('quarantine-queue-resize').type(
            '{rightarrow}{rightarrow}{rightarrow}'
        );
        cy.getBySel('quarantine-queue').invoke('outerWidth').should('eq', 320);
        cy.visit(assetsUrl());
        cy.visit(`${databoxUrl}/quarantine`);
        cy.getBySel('quarantine-queue', {timeout: 30000})
            .invoke('outerWidth')
            .should('eq', 320);
    });
});
