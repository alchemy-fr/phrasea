/**
 * Feature 14 — Display profiles: switched from the settings sub-menu, edited
 * by drag & drop (asset attributes, grid card).
 *
 * dnd-kit listens to pointer events: a drag is a `pointerdown` on the source,
 * `pointermove`s beyond the activation distance, then `pointerup` (see
 * `28-drag-drop`).
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {login, openSettingsMenu, routeDialog, visitWorkspace, waitForResults} from './lib/app';

const pointer = {button: 0, buttons: 1, isPrimary: true, pointerId: 1, pointerType: 'mouse', force: true};

function point($el, where = 'center') {
    const r = $el[0].getBoundingClientRect();

    return {x: r.left + r.width / 2, y: where === 'top' ? r.top + 3 : r.top + r.height / 2};
}

function move(x, y) {
    cy.get('body', {withinSubject: null}).trigger('pointermove', {...pointer, clientX: x, clientY: y, eventConstructor: 'PointerEvent'});
}

/** Drags `source` onto `target` (its center, or near its top edge) */
function dragTo(source, target, where = 'center') {
    source.scrollIntoView().then($src => {
        const {x, y} = point($src);
        cy.wrap($src).trigger('pointerdown', {...pointer, clientX: x, clientY: y, eventConstructor: 'PointerEvent'});
        move(x + 8, y + 8);
        move(x + 16, y + 16);
    });
    // The ghost ignores pointer events: Cypress' visibility check cannot see it
    cy.get('[data-testid=profile-drag-ghost]', {withinSubject: null}).should('exist');
    target.then($t => {
        const {x, y} = point($t, where);
        move(x, y);
        cy.wait(100, {log: false});
        move(x + 1, y);
    });
    cy.get('body', {withinSubject: null}).trigger('pointerup', {...pointer, buttons: 0, eventConstructor: 'PointerEvent'});
    cy.get('[data-testid=profile-drag-ghost]', {withinSubject: null}).should('not.exist');
}

/** Switching profile resets the preferences, and the search URL with them */
function waitForSwitch() {
    cy.wait('@preferences');
    cy.wait(500, {log: false});
}

/** An attribute of the palette of a tab (the visited tabs stay mounted, hidden) */
function paletteEntry(tab, label) {
    return cy.get(`[data-testid=dialog-tab-${tab}] [data-testid=palette-entry]`).contains(label).closest('[data-testid=palette-entry]');
}

/** The draggable part of a displayed attribute (the row, but its options) */
function displayedRow(which) {
    return cy.getBySel('profile-displayed-item')[which]().find('[aria-roledescription=sortable]');
}

function openProfileMenu() {
    openSettingsMenu();
    cy.getBySel('profile-menu').click();
    cy.getBySel('profile-manage').should('be.visible');
}

describe('Display profiles', () => {
    let ctx;
    const profileName = `E2E profile ${Date.now()}`;

    before(() => {
        login();
        seedWorkspace({assets: 2}).then(c => {
            ctx = c;
        });
    });

    after(() => {
        if (ctx) {
            deleteWorkspace(ctx.workspace.id);
        }
    });

    beforeEach(() => {
        cy.intercept('PUT', '**/preferences').as('preferences');
        cy.viewport(1400, 900);
        login();
        visitWorkspace(ctx.workspace.id);
        waitForResults(2);
    });

    it('creates a profile and switches profiles from the sub-menu', () => {
        openProfileMenu();
        cy.getBySel('profile-create').click();
        cy.dialog('last').within(() => {
            cy.fieldByLabel('Name').type(profileName);
            cy.contains('button', 'Create').click();
        });
        // The new profile is selected and opened in its editor
        routeDialog().within(() => {
            cy.contains('[role=tab]', 'Asset attributes').should('have.attr', 'aria-selected', 'true');
        });
        cy.get('body').type('{esc}');
        cy.getBySel('route-dialog').should('not.exist');

        openProfileMenu();
        cy.get('[role=menuitemradio]').contains(profileName).closest('[role=menuitemradio]').should('have.attr', 'aria-checked', 'true');
        cy.getBySel('profile-default').click();
        waitForSwitch();
        openProfileMenu();
        cy.getBySel('profile-default').should('have.attr', 'aria-checked', 'true');
        cy.get('[role=menuitemradio]').contains(profileName).click();
        waitForSwitch();
        openSettingsMenu();
        cy.getBySel('profile-menu').should('contain', profileName);
        cy.get('body').type('{esc}');
    });

    it('organizes the asset attributes by drag & drop', () => {
        cy.intercept('POST', '**/profiles/*/items').as('add');
        cy.intercept('POST', '**/profiles/*/sort').as('sort');
        cy.intercept('POST', '**/profiles/*/remove').as('remove');
        openProfileMenu();
        cy.getBySel('profile-edit-current').click();
        routeDialog().within(() => {
            cy.contains('[role=tab]', 'Asset attributes').should('have.attr', 'aria-selected', 'true');
            // From the palette to the (empty) list
            dragTo(paletteEntry('attributes', 'Description'), cy.getBySel('profile-displayed'));
            cy.wait('@add');
            cy.getBySel('profile-displayed-item').should('have.length', 1).first().should('contain', 'Description');
            // A divider dropped above it
            dragTo(cy.get('[data-testid=dialog-tab-attributes] [data-key="l:divider"]'), cy.getBySel('profile-displayed-item').first(), 'top');
            cy.wait(['@add', '@sort']);
            cy.getBySel('profile-displayed-item').should('have.length', 2).first().should('contain', 'Divider');
            // Reordered: the divider below the description
            dragTo(displayedRow('first'), cy.getBySel('profile-displayed-item').last());
            cy.getBySel('profile-displayed-item').last().should('contain', 'Divider');
            // Dragged back to the palette right away (the reorder may still
            // be in flight): removed
            dragTo(displayedRow('last'), cy.get('[data-testid=dialog-tab-attributes] [data-testid=profile-palette]'));
            cy.wait(['@sort', '@remove']);
            cy.getBySel('profile-displayed-item').should('have.length', 1).first().should('contain', 'Description');
        });
        // Persisted
        cy.reload();
        routeDialog().within(() => {
            cy.getBySel('profile-displayed-item').should('have.length', 1).first().should('contain', 'Description');
        });
    });

    it('lays out the grid card by drag & drop', () => {
        cy.intercept('POST', '**/profiles/*/items').as('add');
        openProfileMenu();
        cy.getBySel('profile-edit-current').click();
        routeDialog().within(() => {
            cy.contains('[role=tab]', 'Grid card').click();
            cy.getBySel('profile-grid-card').should('be.visible');
            dragTo(paletteEntry('grid', 'Description'), cy.get('[data-zone="cell:below:l"]'));
            cy.wait('@add');
            cy.get('[data-zone="cell:below:l"]').should('contain', 'Description');
            // Moved to another zone
            dragTo(cy.get('[data-zone="cell:below:l"] [data-testid=profile-grid-item]'), cy.get('[data-zone="cell:over:tc"]'));
            cy.get('[data-zone="cell:over:tc"]').should('contain', 'Description');
            cy.get('[data-zone="cell:below:l"]').should('not.contain', 'Description');
        });
    });

    it('shows the pinned attributes in the viewer', () => {
        cy.getBySel('asset-item').first().dblclick();
        cy.getBySel('asset-view', {timeout: 30000}).within(() => {
            cy.contains('Description').should('be.visible');
            cy.contains('description').should('be.visible');
        });
        cy.get('body').type('{esc}');
    });

    it('deletes the profile', () => {
        openProfileMenu();
        cy.getBySel('profile-manage').click();
        cy.dialog().contains('[data-testid=profile-row]', profileName).find('[data-testid=profile-row-menu]').click();
        cy.menuItem('Delete').click();
        cy.dialog('last').contains('button', /Confirm|Delete/).click();
        cy.dialog().contains(profileName).should('not.exist');
    });
});
