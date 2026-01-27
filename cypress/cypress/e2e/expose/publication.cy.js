import {exposeUrl} from '../lib/urls';

describe('Visit publication', () => {
    it('is responsive lightbox', () => {
        cy.visit(`${exposeUrl}/test-pub`);
        cy.contains('Test publication');
        cy.contains('This is a test publication.');

        cy.get('.thumbContainer').eq(0).click();
        cy.contains('A wide image');
        cy.contains('This is a wide image');

        cy.get('.lightbox').should('be.visible');
        cy.get('.lb-media-container').should('be.visible');

        const assertDimensionEquals = (dimension, expected, mediaSelector) => {
            const sel = '.lb-media-container ' + mediaSelector;
            cy.get(sel)
                .invoke(dimension)
                .should('be.lte', Math.ceil(expected + 1))
                .and('be.gte', Math.floor(expected - 1));
        };

        const assertDimensionsEquals = (width, height, mediaSelector) => {
            assertDimensionEquals('innerWidth', width, mediaSelector);
            assertDimensionEquals('innerHeight', height, mediaSelector);
        };

        let ratio = 100 / 1000;
        cy.viewport(320, 480); // Mobile
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(320, 320 * ratio, 'img');

        cy.viewport(768, 1024); // Tablet
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(768, 768 * ratio, 'img');

        cy.viewport(1280, 800); // Desktop
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(1000, 1000 * ratio, 'img');

        cy.get('body').trigger('keydown', {key: 'ArrowRight'});
        cy.contains('A tall image');
        cy.contains('This is a tall image');
        ratio = 1000 / 100;

        cy.viewport(320, 480); // Mobile
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(28.8555, 28.8555 * ratio, 'img');

        cy.viewport(768, 1024); // Tablet
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(61.4335, 61.4335 * ratio, 'img');

        cy.viewport(1280, 800); // Desktop
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(68.8555, 68.8555 * ratio, 'img');

        cy.get('body').trigger('keydown', {key: 'ArrowLeft'});
        cy.contains('A wide image');
        cy.contains('This is a wide image');

        cy.get('body').trigger('keydown', {key: 'ArrowRight'});
        cy.get('body').trigger('keydown', {key: 'ArrowRight'});
        cy.contains('A test audio');
        cy.contains('This is a test audio');
        cy.get('body').trigger('keydown', {key: 'ArrowRight'});
        cy.contains('A classic video');
        cy.contains('This is a classic video');

        cy.get('.lb-media-container video').should('be.visible');
        cy.get('video')
            .should('have.prop', 'paused', true)
            .and('have.prop', 'ended', false);

        cy.get('video').then($video => {
            return new Cypress.Promise(resolve => {
                $video.on('loadedmetadata', () => {
                    resolve();
                });
            });
        });

        ratio = 1440 / 2560;
        cy.viewport(320, 480); // Mobile
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(320, 320 * ratio, 'video');

        cy.viewport(768, 1024); // Tablet
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(768, 768 * ratio, 'video');

        cy.viewport(1280, 800); // Desktop
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(1073.96555, 1073.96555 * ratio, 'video');

        cy.get('body').trigger('keydown', {key: 'ArrowRight'});
        cy.contains('A tall video');
        cy.contains('This is a tall video');
        cy.get('.lb-media-container video').should('be.visible');
        cy.get('video')
            .should('have.prop', 'paused', true)
            .and('have.prop', 'ended', false);

        cy.get('video').then($video => {
            return new Cypress.Promise(resolve => {
                $video.on('loadedmetadata', () => {
                    resolve();
                });
            });
        });

        ratio = 4096 / 2160;
        cy.viewport(320, 480); // Mobile
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(151.88555, 151.88555 * ratio, 'video');

        cy.viewport(768, 1024); // Tablet
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(324, 324 * ratio, 'video');

        cy.viewport(1280, 800); // Desktop
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(362.81555, 362.81555 * ratio, 'video');

        cy.get('body').trigger('keydown', {key: 'ArrowRight'});
        cy.contains('A one page PDF document');
        cy.contains('This is a one page PDF document');

        ratio = 11 / 8.5;
        cy.viewport(320, 480); // Mobile
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(222, 222 * ratio, '.react-pdf__Document');

        cy.viewport(768, 1024); // Tablet
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(222, 222 * ratio, '.react-pdf__Document');

        cy.viewport(1280, 800); // Desktop
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(531, 531 * ratio, '.react-pdf__Document');

        cy.get('body').trigger('keydown', {key: 'ArrowRight'});
        cy.contains('A two page PDF document');
        cy.contains('This is a two page PDF document');

        ratio = 8.50 / 11.93;
        cy.viewport(320, 480); // Mobile
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(320, 320 * ratio, '.react-pdf__Document');

        cy.viewport(768, 1024); // Tablet
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(768, 768 * ratio, '.react-pdf__Document');

        cy.viewport(1280, 800); // Desktop
        cy.get('.lightbox').should('be.visible');
        assertDimensionsEquals(965, 965 * ratio, '.react-pdf__Document');

        // Close lightbox
        cy.get('.lb-close').click();
        cy.get('.lightbox').should('not.exist');
    });
});
