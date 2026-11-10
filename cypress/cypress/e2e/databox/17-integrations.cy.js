/**
 * Feature 17 — Integrations: workspace administration and asset side panel.
 */
import {apiRequest, deleteWorkspace, seedWorkspace, uploadAssetFromFixture, waitForAsset} from './lib/api';
import {expectToastText, login, routeDialog} from './lib/app';
import {databoxUrl} from '../lib/urls';

describe('Integrations', () => {
    let ctx;
    let image;
    let photoEditor;

    before(() => {
        login();
        seedWorkspace({assets: 1})
            .then(c => {
                ctx = c;

                return uploadAssetFromFixture(ctx.workspace.id, 'e2e-image.png', {name: 'E2E Integrations'});
            })
            .then(a => {
                image = a;

                return waitForAsset(image.id, asset => !!asset.source && !!asset.thumbnail, 'renditions');
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

    it('creates a workspace integration', () => {
        cy.visit(`${databoxUrl}/workspaces/${ctx.workspace.id}/manage/integrations`);
        // The tab renders once the integration types are loaded
        cy.getBySel('definition-create', {timeout: 30000}).should('be.visible');
        routeDialog().within(() => {
            cy.getBySel('definition-create').click();
            // The type is picked in the catalog, then the form shows up
            cy.getBySel('integration-catalog-item').first().click();
            cy.fieldByLabel('Title').type('E2E integration');
            // The configuration form is generated from the schema of the type
            cy.getBySel('integration-config-form').should('be.visible');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Integration saved');
        routeDialog().getBySel('definition-item').should('contain', 'E2E integration');
    });

    it('sets up face recognition from the form', () => {
        cy.visit(`${databoxUrl}/workspaces/${ctx.workspace.id}/manage/integrations`);
        cy.getBySel('definition-create', {timeout: 30000}).should('be.visible');
        routeDialog().within(() => {
            cy.getBySel('definition-create').click();
            cy.get('[data-integration="core.face_recognition"]').click();
            cy.fieldByLabel('Title').type('E2E faces');
            // The attribute is picked among the entity attributes, or created on the spot
            cy.getBySel('config-field-attribute').find('[role=combobox]').click();
        });
        cy.get('[cmdk-input]').type('Faces');
        cy.contains('[cmdk-item]', 'Create “Faces”').click();
        cy.getBySel('form-dialog')
            .last()
            .within(() => {
                cy.fieldByLabel('Name').should('have.value', 'Faces');
                cy.fieldByLabel('Type').should('be.disabled');
                cy.getBySel('create-attribute-multiple').should('have.attr', 'aria-checked', 'true').and('be.disabled');
                // No entity list yet: a new one is proposed
                cy.fieldByLabel('List name').should('have.value', 'Persons');
                cy.contains('button', 'Create').click();
            });
        expectToastText('Attribute created');
        routeDialog().within(() => {
            cy.getBySel('config-field-attribute').should('contain', 'Faces');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Integration saved');

        // Written by the form, read back as YAML
        routeDialog().within(() => {
            cy.getBySel('integration-config-mode-yaml').click();
            cy.contains('attribute: faces').should('be.visible');
        });
        apiRequest({
            method: 'GET',
            path: `/attribute-definitions?workspaceId=${ctx.workspace.id}`,
        }).then(page => {
            const faces = (page.member ?? []).find(d => d.slug === 'faces');
            expect(faces, 'faces attribute').to.exist;
            expect(faces.type).to.equal('entity');
            expect(faces.multiple).to.equal(true);
            expect(faces.entityList).to.exist;
        });
    });

    it('saves a typed key as a secret of the workspace', () => {
        cy.visit(`${databoxUrl}/workspaces/${ctx.workspace.id}/manage/integrations`);
        cy.getBySel('definition-create', {timeout: 30000}).should('be.visible');
        routeDialog().within(() => {
            cy.getBySel('definition-create').click();
            cy.get('[data-integration="remove.bg"]').click();
            cy.fieldByLabel('Title').type('E2E remove.bg');
            // A secret typed in clear is masked, and offered to be saved as a secret
            cy.get('#config-apiKey').should('have.attr', 'type', 'password').type('e2e-remove-bg-key');
            cy.getBySel('config-save-secret-apiKey').click();
        });
        cy.getBySel('form-dialog')
            .last()
            .within(() => {
                cy.get('#create-secret-name').should('have.value', 'API_KEY');
                cy.contains('button', 'Create').click();
            });
        expectToastText('Secret created');
        routeDialog().within(() => {
            // The field now refers to the secret
            cy.getBySel('config-mode-reference-apiKey').should('have.attr', 'aria-selected', 'true');
            cy.getBySel('secret-ref-chip').should('have.text', 'API_KEY').and('have.attr', 'data-kind', 'secret');
            cy.getBySel('secret-ref-status').should('contain', 'Secret');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Integration saved');

        apiRequest({
            method: 'GET',
            path: `/integrations?workspace=${ctx.workspace.id}`,
        }).then(page => {
            const integration = (page.member ?? []).find(i => i.name === 'E2E remove.bg');
            expect(integration, 'remove.bg integration').to.exist;
            expect(integration.configValues.apiKey).to.equal('${API_KEY}');
        });

        // Listed in the Secrets tab, with the integration using it, never with its value
        cy.visit(`${databoxUrl}/workspaces/${ctx.workspace.id}/manage/secrets`);
        routeDialog()
            .getBySel('secret-API_KEY', {timeout: 30000})
            .should('contain', 'Used by E2E remove.bg')
            .and('not.contain', 'e2e-remove-bg-key');
    });

    it('manages the variables of the workspace, shown in clear', () => {
        cy.visit(`${databoxUrl}/workspaces/${ctx.workspace.id}/manage/secrets`);
        routeDialog().within(() => {
            cy.getBySel('variable-create', {timeout: 30000}).click();
        });
        cy.getBySel('form-dialog')
            .last()
            .within(() => {
                cy.get('#create-variable-name').type('E2E_HOST');
                cy.get('#create-variable-value').type('e2e.example.test');
                cy.contains('button', 'Create').click();
            });
        expectToastText('Variable created');
        routeDialog().getBySel('variable-E2E_HOST').should('contain', 'e2e.example.test');

        routeDialog().within(() => {
            cy.getBySel('variable-edit-E2E_HOST').click();
        });
        cy.getBySel('form-dialog')
            .last()
            .within(() => {
                cy.get('#edit-variable-value').should('have.value', 'e2e.example.test').clear().type('e2e.other.test');
                cy.contains('button', 'Save').click();
            });
        expectToastText('Variable saved');
        routeDialog().getBySel('variable-E2E_HOST').should('contain', 'e2e.other.test');
    });

    it('duplicates an integration, disabled', () => {
        cy.visit(`${databoxUrl}/workspaces/${ctx.workspace.id}/manage/integrations`);
        cy.getBySel('definition-create', {timeout: 30000}).should('be.visible');
        routeDialog().within(() => {
            cy.getBySel('definition-item')
                .contains('E2E integration')
                .closest('[data-testid=definition-item]')
                .find('[data-testid=definition-menu]')
                .click({force: true});
        });
        // The menu is portaled to <body>, out of the dialog
        cy.getBySel('definition-duplicate').click();
        expectToastText('Copy created');
        routeDialog().within(() => {
            // The copy is selected and its form opened
            cy.getBySel('definition-item').contains('E2E integration (copy)').closest('[data-testid=definition-item]').should('have.class', 'border-primary');
            cy.fieldByLabel('Title').should('have.value', 'E2E integration (copy)');
            // Not to be triggered twice
            cy.fieldByLabel('Enabled').should('have.attr', 'aria-checked', 'false');
        });
    });

    it('shows one tab per integration of the file in the asset viewer', () => {
        apiRequest({
            method: 'POST',
            path: '/integrations',
            body: {
                workspace: `/workspaces/${ctx.workspace.id}`,
                integration: 'tui.photo-editor',
                name: 'E2E photo editor',
                enabled: true,
                public: false,
            },
        }).then(integration => {
            photoEditor = integration;
        });

        // Each integration is a tab of the side panel
        cy.then(() => cy.visit(`${databoxUrl}/assets/${image.id}/_#panel=integration-${photoEditor.id}`));
        cy.getBySel('asset-view', {timeout: 30000})
            .find('[data-testid=asset-integration]', {timeout: 30000})
            .should('be.visible')
            .and('contain', 'E2E photo editor')
            .and('contain', 'Toast UI Photo Editor');
    });

    it('edits the displayed file with the Toast UI photo editor', () => {
        cy.visit(`${databoxUrl}/assets/${image.id}/_#panel=integration-${photoEditor.id}`);
        cy.getBySel('asset-view', {timeout: 30000}).within(() => {
            cy.contains('button', 'Open photo editor', {timeout: 30000}).click();
        });

        // The editor is a full screen dialog over the viewer
        cy.get('.tui-image-editor-container', {timeout: 60000}).should('be.visible');
        cy.dialog('last').within(() => {
            cy.get('input[placeholder="File name"]').type('E2E edited');
            cy.contains('button', 'Save as').click();
        });
        expectToastText('Saved!');

        // The export is listed in the panel, ready to be reopened
        cy.getBySel('asset-view').contains('E2E edited', {timeout: 30000}).should('be.visible');
    });
});
