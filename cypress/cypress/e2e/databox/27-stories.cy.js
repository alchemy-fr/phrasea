/**
 * Feature 27 — Stories in the viewer: the carousel switches between the items
 * of the story without losing it, and a story with no rendition of its own
 * displays its first item.
 */
import {createAsset, deleteWorkspace, getAsset, seedWorkspace, uploadAssetFromFixture, waitForAsset, waitForIndexed} from './lib/api';
import {login, visitAssetView} from './lib/app';
import {databoxUrl} from '../lib/urls';

describe('Stories', () => {
    let ctx;
    let story;
    let item;
    let otherItem;

    before(() => {
        login();
        seedWorkspace({assets: 0})
            .then(c => {
                ctx = c;

                return createAsset(ctx.workspace.id, {name: 'E2E Story', isStory: true});
            })
            .then(s => getAsset(s.id))
            .then(s => {
                story = s;

                // The items of a story live in the story's own collection
                return uploadAssetFromFixture(ctx.workspace.id, 'e2e-image.png', {
                    name: 'Story item 1',
                    collection: `/collections/${story.storyCollection.id}`,
                });
            })
            .then(a => {
                item = a;

                return waitForAsset(a.id, asset => !!asset.preview, 'renditions of the first story item');
            })
            .then(() =>
                createAsset(ctx.workspace.id, {
                    name: 'Story item 2',
                    collection: `/collections/${story.storyCollection.id}`,
                })
            )
            .then(a => {
                otherItem = a;
            })
            .then(() => waitForIndexed({story: story.id}, 2));
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

    it('displays the first item of a story that has no rendition', () => {
        visitAssetView(story.id);
        cy.getBySel('story-carousel', {timeout: 30000}).should('be.visible');
        cy.getBySel('asset-view').find('img').should('be.visible');
        cy.getBySel('asset-view').contains('No rendition available yet').should('not.exist');
    });

    it('switches between the items of a story without losing it', () => {
        visitAssetView(story.id);
        cy.getBySel('story-carousel', {timeout: 30000})
            .find('button[title^="Story item"]')
            .then($items => {
                // Follow the order of the carousel
                const names = $items.toArray().map(el => el.title);
                expect(names).to.have.length(2);

                cy.getBySel('story-carousel').find(`button[title="${names[1]}"]`).click();
                cy.getBySel('asset-view-title').should('have.text', names[1]);
                cy.location('pathname').should('not.include', story.id);
                cy.location('search').should('eq', `?story=${story.id}`);
                // Still browsing the story: the carousel stays, current item marked
                cy.getBySel('story-carousel').find(`button[title="${names[1]}"]`).should('have.attr', 'aria-current', 'true');

                // Previous / next now walk the items of the story
                cy.get('[aria-label="Previous"]').click();
                cy.getBySel('asset-view-title').should('have.text', names[0]);
                cy.getBySel('story-carousel').should('be.visible');

                // Back to the story itself
                cy.getBySel('story-cover').click();
                cy.location('pathname').should('include', story.id);
                cy.location('search').should('eq', '');
                cy.getBySel('story-cover').should('have.attr', 'aria-current', 'true');
            });
    });

    it('keeps the story of an item on reload', () => {
        visitAssetView(story.id);
        cy.getBySel('story-carousel', {timeout: 30000}).find(`button[title="${item.name}"]`).click();
        cy.getBySel('asset-view-title').should('have.text', item.name);
        cy.location('search').should('eq', `?story=${story.id}`);

        cy.reload();
        cy.getBySel('asset-view-title', {timeout: 30000}).should('have.text', item.name);
        cy.getBySel('story-carousel', {timeout: 30000})
            .find(`button[title="${item.name}"]`)
            .should('have.attr', 'aria-current', 'true');
        cy.location('search').should('eq', `?story=${story.id}`);
    });

    it('drops an unknown story from the URL', () => {
        // The other item is not a story, the first item is not one of its items
        cy.visit(`${databoxUrl}/assets/${item.id}/_?story=${otherItem.id}`);
        cy.getBySel('asset-view-title', {timeout: 30000}).should('have.text', item.name);
        cy.location('search').should('eq', '');
        cy.getBySel('story-carousel').should('not.exist');
    });
});
