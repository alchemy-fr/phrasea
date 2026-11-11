/**
 * Uploader — admin editors: the form and the data of each target.
 */
import {uploaderUrl} from '../lib/urls';
import {
    ensureTarget,
    FORM_FIELDS,
    getForm,
    getTargetData,
    removeForm,
    removeTargetData,
    setBuilderForm,
    setTargetData,
    TARGETS,
} from './lib/api';
import {confirmDialog, expectToastText, login, setCodeEditorValue} from './lib/app';

function row(targetName) {
    return cy.contains('[data-testid=admin-target-row]', targetName, {timeout: 30000});
}

/** The badge of the target in the list: `on` when it has a form (or data) */
function badgeIs(targetName, state) {
    row(targetName).findBySel('admin-target-badge').should('have.attr', 'data-state', state);
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
            cy.visit(`${uploaderUrl}/admin/forms`);
            badgeIs(TARGETS.admin.name, 'on');
            row(TARGETS.admin.name).findBySel('admin-target-edit').click();

            cy.url().should('include', `/admin/forms/${target.id}`);
            cy.getBySel('page-title').should('contain', `Form of ${TARGETS.admin.name}`);
            cy.getBySel('form-builder-editor').should('be.visible');
            cy.getBySel('builder-palette').should('be.visible');
            cy.getBySel('builder-canvas').within(() => {
                cy.contains('About the files').should('exist');
                cy.contains('Album').should('exist');
                cy.contains('Copies').should('exist');
            });
            cy.getBySel('admin-save').should('be.disabled');

            cy.getBySel('form-builder-preview-tab').click();
            cy.getBySel('form-preview').within(() => {
                cy.contains('label', 'Album').should('be.visible');
                cy.contains('Notify me when done').should('be.visible');
            });
        });

        it('creates the form of a target without one', () => {
            removeForm(target.id);
            cy.visit(`${uploaderUrl}/admin/forms`);
            badgeIs(TARGETS.admin.name, 'off');
            row(TARGETS.admin.name).findBySel('admin-target-edit').click();

            cy.getBySel('form-builder-editor').should('be.visible');
            cy.getBySel('form-delete').should('not.exist');
            cy.getBySel('builder-canvas-field').should('not.exist');
            cy.getBySel('builder-palette').find('button[aria-label="Add Long text"]').click();
            cy.getBySel('builder-canvas-field').should('have.length', 1);
            cy.getBySel('admin-save').should('be.enabled').click();

            expectToastText('Form saved');
            cy.getBySel('form-delete').should('be.visible');
            getForm(target.id).then(form => {
                expect(form.fields).to.have.length(1);
                expect(form.fields[0]).to.include({source: 'custom', key: 'textarea', type: 'textarea'});
            });

            cy.getBySel('admin-back').click();
            badgeIs(TARGETS.admin.name, 'on');
        });

        it('deletes the form of a target', () => {
            setBuilderForm(target.id, FORM_FIELDS);
            cy.visit(`${uploaderUrl}/admin/forms/${target.id}`);
            cy.getBySel('builder-canvas', {timeout: 30000}).should('contain', 'Album');
            cy.getBySel('form-delete').click();
            confirmDialog('Delete');

            expectToastText('Form deleted');
            cy.getBySel('form-delete').should('not.exist');
            cy.getBySel('builder-canvas').should('not.contain', 'Album');
            getForm(target.id).should('be.null');

            cy.getBySel('admin-back').click();
            badgeIs(TARGETS.admin.name, 'off');
        });
    });

    describe('Target data', () => {
        it('sets the data of a target', () => {
            removeTargetData(target.id);
            cy.visit(`${uploaderUrl}/admin/target-data`);
            badgeIs(TARGETS.admin.name, 'off');
            row(TARGETS.admin.name).findBySel('admin-target-edit').click();

            cy.url().should('include', `/admin/target-data/${target.id}`);
            cy.getBySel('page-title').should('contain', `Target Data of ${TARGETS.admin.name}`);
            setCodeEditorValue('target-data-editor', '{"my_var": "my_value"}');
            cy.getBySel('admin-save').click();

            expectToastText('Target data saved');
            getTargetData(target.id).should('deep.eq', {my_var: 'my_value'});

            cy.getBySel('admin-back').click();
            badgeIs(TARGETS.admin.name, 'on');
        });

        it('removes the data of a target once emptied', () => {
            setTargetData(target.id, {e2e: true});
            cy.visit(`${uploaderUrl}/admin/target-data/${target.id}`);
            cy.getBySel('target-data-editor', {timeout: 30000}).should('contain', '"e2e"');
            setCodeEditorValue('target-data-editor', '');
            cy.getBySel('admin-save').click();

            expectToastText('Target data saved');
            getTargetData(target.id).should('be.null');

            cy.getBySel('admin-back').click();
            badgeIs(TARGETS.admin.name, 'off');
        });
    });
});
