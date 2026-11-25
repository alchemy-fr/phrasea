/**
 * Uploader — admin editors: the targets, and the form and the data of each.
 */
import {uploaderUrl} from '../lib/urls';
import {
    apiRequest,
    ensureTarget,
    FORM_FIELDS,
    getForm,
    getTargetData,
    iri,
    removeForm,
    removeTargetData,
    setBuilderForm,
    setTargetData,
    TARGETS,
    uniqueValue,
} from './lib/api';
import {confirmDialog, expectToastText, login, setCodeEditorValue} from './lib/app';

function row(targetName) {
    return cy.contains('[data-testid=admin-target-row]', targetName, {timeout: 30000});
}

/** A badge of the target in the list (`form` or `data`): `on` when the target has it */
function badgeIs(targetName, kind, state) {
    row(targetName).find(`[data-testid=admin-target-badge][data-badge=${kind}]`).should('have.attr', 'data-state', state);
}

/** Each tab of the target editor has its own save button */
function saveButton(editorTestId) {
    return cy.getBySel(editorTestId).findBySel('admin-save');
}

function codeEditorValue(testId) {
    return cy
        .getBySel(testId)
        .find('.ace_editor', {timeout: 30000})
        .then($el => $el[0].env.editor.getValue());
}

describe('Uploader admin', () => {
    let target;

    before(() => {
        login();
        ensureTarget(TARGETS.admin).then(t => {
            target = t;
        });
    });

    beforeEach(() => {
        cy.viewport(1400, 900);
        login();
    });

    describe('Forms', () => {
        it('lists the targets and opens the form of one in the builder', () => {
            setBuilderForm(target.id, FORM_FIELDS);
            cy.visit(`${uploaderUrl}/admin/targets`);
            badgeIs(TARGETS.admin.name, 'form', 'on');
            row(TARGETS.admin.name).findBySel('admin-target-edit').click();

            cy.url().should('include', `/admin/targets/${target.id}`);
            cy.getBySel('page-title').should('contain', `Edit ${TARGETS.admin.name}`);
            cy.getBySel('form-builder-editor').should('be.visible');
            cy.getBySel('builder-palette').should('be.visible');
            cy.getBySel('builder-canvas').within(() => {
                cy.contains('About the files').should('exist');
                cy.contains('Album').should('exist');
                cy.contains('Copies').should('exist');
            });
            saveButton('form-builder-editor').should('be.disabled');

            cy.getBySel('form-builder-preview-tab').click();
            cy.getBySel('form-preview').within(() => {
                cy.contains('label', 'Album').should('be.visible');
                cy.contains('Notify me when done').should('be.visible');
            });
        });

        it('creates the form of a target without one', () => {
            removeForm(target.id);
            cy.visit(`${uploaderUrl}/admin/targets`);
            badgeIs(TARGETS.admin.name, 'form', 'off');
            row(TARGETS.admin.name).findBySel('admin-target-edit').click();

            cy.getBySel('form-builder-editor').should('be.visible');
            cy.getBySel('form-delete').should('not.exist');
            cy.getBySel('builder-canvas-field').should('not.exist');
            cy.getBySel('builder-palette').find('button[aria-label="Add Long text"]').click();
            cy.getBySel('builder-canvas-field').should('have.length', 1);
            saveButton('form-builder-editor').should('be.enabled').click();

            expectToastText('Form saved');
            cy.getBySel('form-delete').should('be.visible');
            getForm(target.id).then(form => {
                expect(form.fields).to.have.length(1);
                expect(form.fields[0]).to.include({source: 'custom', key: 'textarea', type: 'textarea'});
            });

            cy.getBySel('admin-back').click();
            badgeIs(TARGETS.admin.name, 'form', 'on');
        });

        it('deletes the form of a target', () => {
            setBuilderForm(target.id, FORM_FIELDS);
            cy.visit(`${uploaderUrl}/admin/targets/${target.id}`);
            cy.getBySel('builder-canvas', {timeout: 30000}).should('contain', 'Album');
            cy.getBySel('form-delete').click();
            confirmDialog('Delete');

            expectToastText('Form deleted');
            cy.getBySel('form-delete').should('not.exist');
            cy.getBySel('builder-canvas').should('not.contain', 'Album');
            getForm(target.id).should('be.null');

            cy.getBySel('admin-back').click();
            badgeIs(TARGETS.admin.name, 'form', 'off');
        });
    });

    describe('Target data', () => {
        it('sets the data of a target', () => {
            removeTargetData(target.id);
            cy.visit(`${uploaderUrl}/admin/targets`);
            badgeIs(TARGETS.admin.name, 'data', 'off');
            row(TARGETS.admin.name).findBySel('admin-target-edit').click();

            cy.url().should('include', `/admin/targets/${target.id}`);
            cy.getBySel('page-title').should('contain', `Edit ${TARGETS.admin.name}`);
            cy.getBySel('target-tab-data').click();
            setCodeEditorValue('target-data-editor', '{"my_var": "my_value"}');
            saveButton('target-data-editor').click();

            expectToastText('Target data saved');
            getTargetData(target.id).should('deep.eq', {my_var: 'my_value'});

            cy.getBySel('admin-back').click();
            badgeIs(TARGETS.admin.name, 'data', 'on');
        });

        it('removes the data of a target once emptied', () => {
            setTargetData(target.id, {e2e: true});
            cy.visit(`${uploaderUrl}/admin/targets/${target.id}`);
            cy.getBySel('target-tab-data', {timeout: 30000}).click();
            cy.getBySel('target-data-editor').should('be.visible').and('contain', '"e2e"');
            setCodeEditorValue('target-data-editor', '');
            saveButton('target-data-editor').click();

            expectToastText('Target data saved');
            getTargetData(target.id).should('be.null');

            cy.getBySel('admin-back').click();
            badgeIs(TARGETS.admin.name, 'data', 'off');
        });

        it('keeps unsaved data while the form tab is open', () => {
            setBuilderForm(target.id, FORM_FIELDS);
            removeTargetData(target.id);
            cy.visit(`${uploaderUrl}/admin/targets/${target.id}`);
            cy.getBySel('target-tab-data', {timeout: 30000}).click();
            setCodeEditorValue('target-data-editor', '{"kept": true}');
            saveButton('target-data-editor').should('be.enabled');

            cy.getBySel('target-tab-form').click();
            cy.getBySel('form-builder-editor').should('be.visible');
            cy.getBySel('target-data-editor').should('not.be.visible');
            saveButton('form-builder-editor').should('be.disabled');

            cy.getBySel('target-tab-data').click();
            cy.getBySel('target-data-editor').should('be.visible');
            codeEditorValue('target-data-editor').should('eq', '{"kept": true}');
            saveButton('target-data-editor').should('be.enabled');
            getTargetData(target.id).should('be.null');
        });
    });

    describe('Targets', () => {
        it('creates a target and opens its editor', () => {
            const name = uniqueValue('Created target');
            cy.visit(`${uploaderUrl}/admin/targets`);
            cy.getBySel('target-create', {timeout: 30000}).click();
            cy.getBySel('target-create-form').within(() => {
                cy.getBySel('target-name-input').type(name);
                cy.getBySel('target-description-input').type('Made from the client');
                cy.getBySel('target-authorizationKey-input').should('not.exist');
                cy.getBySel('target-targetUrl-input').type('https://example.com/upload');
                cy.getBySel('target-authorizationKey-input').should('be.visible');
                cy.getBySel('target-targetUrl-input').clear();
            });
            cy.contains('button', 'Create the target').click();

            cy.url().should('match', /\/admin\/targets\/[0-9a-f-]{36}$/);
            cy.getBySel('page-title').should('contain', `Edit ${name}`);
            cy.url().then(url => apiRequest({method: 'DELETE', path: iri('targets', url.split('/').pop())}));
        });
    });
});
