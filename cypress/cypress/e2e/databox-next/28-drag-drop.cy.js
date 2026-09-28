/**
 * Feature 28 — Drag & drop: assets dropped on collections, baskets and pinned
 * stories of the sidebar; collections moved within the tree.
 *
 * dnd-kit listens to pointer events: a drag is a `pointerdown` on the card,
 * `pointermove`s beyond the activation distance, then `pointerup`.
 */
import {apiRequest, createAsset, deleteWorkspace, getAsset, seedWorkspace, waitForBasketListed, waitForIndexed} from './lib/api';
import {assetItem, expandTreeWorkspace, expectToastText, login, openAssetContextMenu, openLeftPanelTab, treeCollection, treeWorkspace, visitWorkspace, waitForResults} from './lib/app';

const pointer = {button: 0, buttons: 1, isPrimary: true, pointerId: 1, pointerType: 'mouse', force: true};

function center($el) {
    const r = $el[0].getBoundingClientRect();

    return {x: r.left + r.width / 2, y: r.top + r.height / 2};
}

/** Starts dragging an element: the ghost is shown once the drag is active */
function dragStart(source) {
    source.then($src => {
        const {x, y} = center($src);
        cy.wrap($src).trigger('pointerdown', {...pointer, clientX: x, clientY: y, eventConstructor: 'PointerEvent'});
        cy.get('body').trigger('pointermove', {...pointer, clientX: x + 12, clientY: y + 12, eventConstructor: 'PointerEvent'});
        cy.get('body').trigger('pointermove', {...pointer, clientX: x + 24, clientY: y + 24, eventConstructor: 'PointerEvent'});
    });
    // The ghost ignores pointer events: Cypress' visibility check cannot see it
    cy.getBySel('drag-ghost').should('exist');
}

/** Moves the pointer over an element (two moves: dnd-kit measures on the first) */
function dragOver(target, keys = {}) {
    target.then($t => {
        const {x, y} = center($t);
        cy.get('body').trigger('pointermove', {...pointer, ...keys, clientX: x, clientY: y, eventConstructor: 'PointerEvent'});
        cy.wait(100, {log: false});
        cy.get('body').trigger('pointermove', {...pointer, ...keys, clientX: x + 1, clientY: y, eventConstructor: 'PointerEvent'});
    });
}

function drop(keys = {}) {
    cy.get('body').trigger('pointerup', {...pointer, ...keys, buttons: 0, eventConstructor: 'PointerEvent'});
    cy.getBySel('drag-ghost').should('not.exist');
}

describe('Drag & drop', () => {
    let ctx;
    let story;
    let basket;
    const basketName = `E2E drop basket ${Date.now()}`;

    before(() => {
        login();
        seedWorkspace({assets: 3})
            .then(c => {
                ctx = c;

                return createAsset(ctx.workspace.id, {name: 'E2E Story', isStory: true});
            })
            .then(s => getAsset(s.id))
            .then(s => {
                story = s;

                return apiRequest({method: 'POST', path: '/baskets', body: {name: basketName}});
            })
            .then(b => {
                basket = b;
                waitForBasketListed(basketName);

                return waitForIndexed({'workspaces[]': ctx.workspace.id}, 4);
            });
    });

    after(() => {
        if (basket) {
            apiRequest({method: 'DELETE', path: `/baskets/${basket.id}`, failOnStatusCode: false});
        }
        if (ctx) {
            deleteWorkspace(ctx.workspace.id);
        }
    });

    beforeEach(() => {
        cy.viewport(1400, 900);
        login();
        visitWorkspace(ctx.workspace.id);
        waitForResults(4);
    });

    it('adds the selection to a collection and keeps it selected', () => {
        openLeftPanelTab('Navigation');
        expandTreeWorkspace(ctx.workspace.id);
        treeCollection(ctx.entertainment.id).should('be.visible');

        assetItem('Alpha').click();
        assetItem('Charlie').click({ctrlKey: true});
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length', 2);

        dragStart(assetItem('Alpha'));
        cy.getBySel('drag-ghost').should('contain', '2 asset(s)');
        dragOver(treeCollection(ctx.entertainment.id));
        cy.getBySel('drag-ghost').should('contain', 'Add to Entertainment');
        drop();

        expectToastText('2 asset(s) added to Entertainment');
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length', 2);
        cy.waitUntil(
            () => getAsset(ctx.assets[0].id).then(a => (a.collections ?? []).some(c => c.id === ctx.entertainment.id)),
            {timeout: 30000, message: 'Alpha linked to Entertainment'}
        );
        getAsset(ctx.assets[2].id).then(a => {
            expect((a.collections ?? []).map(c => c.id)).to.include(ctx.entertainment.id);
        });
    });

    it('drags an unselected asset alone to a basket, switching the tab while hovering it', () => {
        openLeftPanelTab('Navigation');
        assetItem('Alpha').click();

        dragStart(assetItem('Bravo'));
        cy.getBySel('drag-ghost').should('contain', '1 asset(s)');
        // Hovering the tab trigger opens the baskets
        dragOver(cy.getBySel('left-panel').find('[role=tab][aria-label="Baskets"]'));
        cy.get(`[data-testid=basket-item][data-basket-id="${basket.id}"]`, {timeout: 20000}).should('be.visible');
        dragOver(cy.get(`[data-testid=basket-item][data-basket-id="${basket.id}"]`));
        cy.getBySel('drag-ghost').should('contain', `Add to ${basketName}`);
        drop();

        expectToastText('1 item(s) added to basket');
        cy.get(`[data-testid=basket-item][data-basket-id="${basket.id}"]`).should('contain', '1');
        apiRequest({path: `/baskets/${basket.id}/assets`}).then(body => {
            expect((body['hydra:member'] ?? []).map(i => i.asset.name)).to.deep.equal(['Bravo']);
        });
    });

    it('pins a story and drops assets on it', () => {
        openAssetContextMenu('E2E Story');
        cy.menuItem('Pin story').click();
        openLeftPanelTab('Navigation');
        cy.get(`[data-testid=pinned-story-item][data-story-id="${story.id}"]`).scrollIntoView().should('be.visible');

        dragStart(assetItem('Charlie'));
        dragOver(cy.get(`[data-testid=pinned-story-item][data-story-id="${story.id}"]`));
        // The seeded name attribute targets assets only: the story has no name
        cy.getBySel('drag-ghost').should('contain', 'Add to');
        drop();

        expectToastText('1 asset(s) added to story');
        cy.waitUntil(
            () => getAsset(ctx.assets[2].id).then(a => (a.collections ?? []).some(c => c.id === story.storyCollection.id)),
            {timeout: 30000, message: 'Charlie in the story'}
        );

        // The story itself cannot be dropped on its own shelf
        dragStart(assetItem('E2E Story'));
        dragOver(cy.get(`[data-testid=pinned-story-item][data-story-id="${story.id}"]`));
        cy.getBySel('drag-ghost').should('contain', 'Cannot be added to itself');
        drop();

        // Unpin from the row menu
        cy.get(`[data-testid=pinned-story-item][data-story-id="${story.id}"]`).rightclick();
        cy.menuItem('Unpin story').click();
        cy.get(`[data-testid=pinned-story-item][data-story-id="${story.id}"]`).should('not.exist');
    });

    it('refuses the assets on their own workspace', () => {
        openLeftPanelTab('Navigation');
        dragStart(assetItem('Alpha'));
        dragOver(treeWorkspace(ctx.workspace.id));
        cy.getBySel('drag-ghost').should('contain', 'Already there');
        drop();
        cy.get('[data-sonner-toast]').should('not.exist');
    });

    it('moves an asset with Shift', () => {
        openLeftPanelTab('Navigation');
        expandTreeWorkspace(ctx.workspace.id);

        dragStart(assetItem('Bravo'));
        dragOver(treeCollection(ctx.sport.id), {shiftKey: true});
        cy.getBySel('drag-ghost').should('contain', 'Move to Sport');
        drop({shiftKey: true});

        expectToastText('Moving 1 asset(s) to Sport');
        cy.waitUntil(
            () =>
                getAsset(ctx.assets[1].id).then(a => {
                    const ids = (a.collections ?? []).map(c => c.id);

                    return ids.includes(ctx.sport.id) && !ids.includes(ctx.football.id);
                }),
            {timeout: 60000, message: 'Bravo moved to Sport'}
        );
    });

    it('moves a collection into another one, never into its own subtree', () => {
        openLeftPanelTab('Navigation');
        expandTreeWorkspace(ctx.workspace.id);
        treeCollection(ctx.sport.id).find('[aria-label=Expand]').click();
        treeCollection(ctx.football.id).should('be.visible');

        // Sport onto Football: Football is its child
        dragStart(treeCollection(ctx.sport.id));
        dragOver(treeCollection(ctx.football.id));
        cy.getBySel('drag-ghost').should('contain', 'Cannot be moved into its own sub-collection');
        drop();

        // Football onto Entertainment
        dragStart(treeCollection(ctx.football.id));
        dragOver(treeCollection(ctx.entertainment.id));
        cy.getBySel('drag-ghost').should('contain', 'Move into Entertainment');
        drop();

        expectToastText('Collection moved');
        cy.waitUntil(
            () => apiRequest({path: `/collections/${ctx.football.id}`}).then(c => c.parentId === ctx.entertainment.id),
            {timeout: 30000, interval: 500, message: 'Football under Entertainment'}
        );
        treeCollection(ctx.entertainment.id).find('[aria-label=Expand]').click();
        treeCollection(ctx.football.id).should('be.visible');

        // Back to the root: drop on the workspace
        dragStart(treeCollection(ctx.football.id));
        dragOver(treeWorkspace(ctx.workspace.id));
        cy.getBySel('drag-ghost').should('contain', `Move into ${ctx.workspace.name}`);
        drop();
        // The toast of the first move may still be there: wait on the API
        cy.waitUntil(
            () => apiRequest({path: `/collections/${ctx.football.id}`}).then(c => !c.parentId),
            {timeout: 30000, interval: 500, message: 'Football back at the root'}
        );
    });
});
