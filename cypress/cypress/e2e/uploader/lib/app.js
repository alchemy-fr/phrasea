/**
 * UI helpers for the Next.js Uploader client.
 */
import {adminPassword, adminUsername, keycloakUrl, uploaderUrl} from '../../lib/urls';

export function loginThroughKeycloak(username = adminUsername, password = adminPassword) {
    cy.origin(keycloakUrl, {args: {username, password}}, ({username, password}) => {
        cy.get('#username').type(username);
        cy.get('#password').type(password, {log: false});
        cy.contains('Sign In').click();
    });
}

/**
 * Logs in once per user and caches the session (localStorage of the app +
 * Keycloak cookies) across specs. The private screens send an anonymous user
 * straight to Keycloak.
 */
export function login(username = adminUsername, password = adminPassword) {
    cy.session(
        ['uploader', username],
        () => {
            cy.visit(uploaderUrl);
            loginThroughKeycloak(username, password);
            cy.getBySel('user-menu', {timeout: 30000}).should('exist');
        },
        {
            cacheAcrossSpecs: true,
            validate: () => {
                cy.visit(uploaderUrl);
                cy.getBySel('user-menu', {timeout: 30000}).should('exist');
            },
        }
    );
}

export const uploadUrl = targetId => `${uploaderUrl}/upload/${targetId}`;

/** The upload screen of a target, once its form is known */
export function visitUpload(targetId) {
    cy.visit(uploadUrl(targetId));
    cy.getBySel('stepper', {timeout: 30000}).should('be.visible');
    cy.getBySel('dropzone').should('be.visible');
}

export function selectFiles(files) {
    cy.getBySel('file-input').selectFile(files, {force: true});
}

/** A PNG file built in the test; its preview is not decoded, only listed */
export function pngFile(fileName, size = 2048) {
    return {contents: Cypress.Buffer.alloc(size, 'x'), fileName, mimeType: 'image/png'};
}

export function fileTile(name) {
    return cy.get(`[data-testid=file-tile][title="${name}"]`);
}

export function builderField(key) {
    return cy.get(`[data-testid=builder-field][data-key="${key}"]`);
}

export function stepIs(label) {
    cy.getBySel('stepper').find('[aria-current=step]').should('contain', label);
}

export function expectToastText(text) {
    const re = text instanceof RegExp ? text : new RegExp(text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

    return cy.get('[data-sonner-toast]', {timeout: 30000}).should($toasts => {
        expect($toasts.text(), 'toast').to.match(re);
    });
}

/**
 * Replaces the text of the code editor inside `testId`, through the Ace API:
 * typed text would get the brackets and quotes Ace closes on its own.
 */
export function setCodeEditorValue(testId, text) {
    cy.getBySel(testId)
        .find('.ace_editor', {timeout: 30000})
        .should($el => {
            expect($el[0].env?.editor, 'Ace editor').to.exist;
        })
        .then($el => {
            $el[0].env.editor.setValue(text, 1);
        });
}

/** Confirms the open confirmation dialog with the given button */
export function confirmDialog(button) {
    cy.dialog().within(() => {
        cy.contains('button', button).click();
    });
    cy.get('[role=dialog]').should('not.exist');
}
